<?php

namespace Laemmi\ContaoGlossaryBundle\Module;

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\CoreBundle\File\Metadata;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Module;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;

class Listing extends Module
{
    /**
     * Display a wildcard in the back end
     * @return string
     */
    public function generate()
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### GLOSSARY LIST ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao?do=modules&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        $this->glossaries = StringUtil::deserialize($this->glossaries);

        // Return if there are no glossaries
        if (!is_array($this->glossaries) || count($this->glossaries) < 1) {
            return '';
        }
        $this->strTemplate = $this->glossaryTpl;

        return parent::generate();
    }

    /**
     * Generate module
     */
    protected function compile()
    {
        $objTerm = $this->Database->execute(
            "SELECT * FROM tl_glossary_term WHERE pid IN(" .
            implode(',', array_map('intval', $this->glossaries)) . ")" . " ORDER BY term"
        );

        if ($objTerm->numRows < 1) {
            $this->Template->terms = [];
            return;
        }

        $arrTerms = [];

        while ($objTerm->next()) {
            $objTemp = new FrontendTemplate();
            $key = mb_substr($objTerm->term, 0, 1);

            $objTemp->term = $objTerm->term;
            $objTemp->anchor = 'gl' . StringUtil::standardize($key);
            $objTemp->id = StringUtil::standardize($objTerm->term);

            $objTemp->definition = StringUtil::encodeEmail((string) $objTerm->definition);
            $objTemp->addImage = false;

            // Add image
            if ($objTerm->addImage && $objTerm->singleSRC) {
                if (!is_numeric($objTerm->singleSRC) && !Validator::isUuid($objTerm->singleSRC)) {
                    $this->Template->hl = 'h1';
                    $this->Template->headline = sprintf(
                        "<p class=\"error\">%s</p>",
                        $GLOBALS['TL_LANG']['ERR']['version2format']
                    );
                } else {
                    $objModel = is_numeric($objTerm->singleSRC)
                        ? FilesModel::findByPk($objTerm->singleSRC)
                        : FilesModel::findByUuid($objTerm->singleSRC);

                    if ($objModel !== null && is_file(System::getContainer()->getParameter('kernel.project_dir') . '/' . $objModel->path)) {
                        $metadata = new Metadata(array_filter([
                            Metadata::VALUE_ALT => $objTerm->alt,
                            Metadata::VALUE_CAPTION => $objTerm->caption,
                        ], static fn ($value) => null !== $value && '' !== $value));

                        $figureBuilder = System::getContainer()
                            ->get('contao.image.studio')
                            ->createFigureBuilder()
                            ->from($objModel)
                            ->setSize($objTerm->size)
                            ->setMetadata($metadata)
                            ->enableLightbox((bool) $objTerm->fullsize);

                        if ($objTerm->imageUrl) {
                            $figureBuilder->setLinkHref($objTerm->imageUrl);
                        }

                        $figure = $figureBuilder->buildIfResourceExists();

                        if (null !== $figure) {
                            $figure->applyLegacyTemplateData($objTemp, null, $objTerm->floating);
                        }
                    }
                }
            }

            $objTemp->enclosures = [];

            // Add enclosures
            if ($objTerm->addEnclosure) {
                Controller::addEnclosuresToTemplate($objTemp, $objTerm->row());
            }

            $arrTerms[$key][] = $objTemp;
        }

        $this->Template->terms = $arrTerms;
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();
        $this->Template->request = $request?->getRequestUri() ?? '/';
        $this->Template->topLink = $GLOBALS['TL_LANG']['MSC']['backToTop'];
    }
}
