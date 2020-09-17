<?php

declare(strict_types=1);

$GLOBALS['TL_DCA']['tl_cowegis_layer']['metasubselectpalettes']['type']['provider'] = [
    'tile_provider',
    'tile_provider_variant',
    'tile_provider_terms_of_use',
];

$GLOBALS['TL_DCA']['tl_cowegis_layer']['fields']['tile_provider'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_cowegis_layer']['tile_provider'],
    'exclude'   => true,
    'inputType' => 'select',
    'eval'      => [
        'mandatory'          => true,
        'tl_class'           => 'w50 clr',
        'includeBlankOption' => true,
        'submitOnChange'     => true,
        'chosen'             => true,
    ],
    'sql'       => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_cowegis_layer']['fields']['tile_provider_variant'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_cowegis_layer']['tile_provider_variant'],
    'exclude'   => true,
    'inputType' => 'select',
    'eval'      => [
        'mandatory'          => true,
        'tl_class'           => 'w50',
        'submitOnChange'     => true,
        'chosen'             => false,
    ],
    'sql'       => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_cowegis_layer']['fields']['tile_provider_terms_of_use'] = [];

$GLOBALS['TL_DCA']['tl_cowegis_layer']['fields']['tile_provider_key'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_cowegis_layer']['tile_provider_key'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_cowegis_layer']['fields']['tile_provider_code'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_cowegis_layer']['tile_provider_code'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
    'sql'       => "varchar(255) NOT NULL default ''",
];
