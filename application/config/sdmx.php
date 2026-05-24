<?php
defined('BASEPATH') OR exit('No direct script access allowed');


$config['sdmx_roles'] = [
    [
        'id'          => 'TIME',
        'label'       => 'Time',
        'description' => 'Time reference for observations (e.g. TIME_PERIOD, reporting dates)',
        'applies_to'  => ['dimension', 'attribute'],
        'rules'       => [
            'max_occurs' => 1,
            'required'   => true
        ]
    ],

    [
        'id'          => 'GEO',
        'label'       => 'Geography',
        'description' => 'Geographic or spatial reference (e.g. REF_AREA, counterpart areas)',
        'applies_to'  => ['dimension', 'attribute'],
        'rules'       => [
            'max_occurs' => 1
        ]
    ],

    [
        'id'          => 'FREQ',
        'label'       => 'Frequency',
        'description' => 'Frequency of data collection or observation',
        'applies_to'  => ['dimension', 'attribute'],
        'rules'       => [
            'max_occurs' => 1
        ]
    ],

    [
        'id'          => 'SERIES',
        'label'       => 'Series / Indicator',
        'description' => 'Identifies the indicator or time series',
        'applies_to'  => ['dimension'],
        'rules'       => [
            'min_occurs' => 1
        ]
    ],

    [
        'id'          => 'OBS_VALUE',
        'label'       => 'Observation Value',
        'description' => 'Primary numeric value being measured',
        'applies_to'  => ['measure'],
        'rules'       => [
            'max_occurs' => 1,
            'required'   => true
        ]
    ],

    [
        'id'          => 'UNIT',
        'label'       => 'Unit',
        'description' => 'Unit of measure, multiplier, or unit type',
        'applies_to'  => ['attribute'],
        'rules'       => [
            'attachment_level' => 'observation'
        ]
    ],

    [
        'id'          => 'STATUS',
        'label'       => 'Status / Flags',
        'description' => 'Status, confidentiality, or release flags',
        'applies_to'  => ['attribute'],
        'rules'       => [
            'attachment_level' => 'observation'
        ]
    ],

    [
        'id'          => 'QUALITY',
        'label'       => 'Quality',
        'description' => 'Quality indicators or break metadata',
        'applies_to'  => ['attribute'],
        'rules'       => [
            'attachment_level' => 'observation'
        ]
    ],

    [
        'id'          => 'CLASSIFICATION',
        'label'       => 'Classification',
        'description' => 'Analytical or categorical breakdowns (sex, age, sector, etc.)',
        'applies_to'  => ['dimension'],
        'rules'       => [
            'max_occurs' => null
        ]
    ],

    [
        'id'          => 'STRUCTURAL',
        'label'       => 'Structural / Technical',
        'description' => 'Technical or presentation-related metadata (decimals, formats)',
        'applies_to'  => ['attribute', 'dimension'],
        'rules'       => [
            'internal_only' => true
        ]
    ],

    [
        'id'          => 'OTHER',
        'label'       => 'Other',
        'description' => 'Free-form or organization-specific metadata',
        'applies_to'  => ['attribute', 'dimension'],
        'rules'       => []
    ]
];


$config['sdmx_concepts'] = [
    // =========================
    // Core / Minimal Gold Standard
    // =========================

    [
        'id'        => 'REF_AREA',
        'label'     => 'Reference area',
        'type'      => 'dimension',
        'category'  => 'core',
        'sdmx_role' => 'GEO',
        'notes'     => 'Geographic reference; ISO / SDMX area codes'
    ],
    [
        'id'        => 'TIME_PERIOD',
        'label'     => 'Time period',
        'type'      => 'dimension',
        'category'  => 'core',
        'sdmx_role' => 'TIME',
        'notes'     => 'Primary time dimension'
    ],
    [
        'id'        => 'INDICATOR',
        'label'     => 'Indicator',
        'type'      => 'dimension',
        'category'  => 'core',
        'sdmx_role' => 'SERIES',
        'notes'     => 'Indicator or series identifier'
    ],
    [
        'id'        => 'OBS_VALUE',
        'label'     => 'Observation value',
        'type'      => 'measure',
        'category'  => 'core',
        'sdmx_role' => 'OBS_VALUE',
        'notes'     => 'Standard SDMX single-measure concept'
    ],
    [
        'id'        => 'UNIT_MEASURE',
        'label'     => 'Unit of measure',
        'type'      => 'attribute',
        'category'  => 'core',
        'sdmx_role' => 'UNIT',
        'notes'     => 'E.g. persons, percent, USD'
    ],
    [
        'id'        => 'UNIT_MULT',
        'label'     => 'Unit multiplier',
        'type'      => 'attribute',
        'category'  => 'core',
        'sdmx_role' => 'UNIT',
        'notes'     => 'Power of 10 multiplier'
    ],
    [
        'id'        => 'DECIMALS',
        'label'     => 'Number of decimals',
        'type'      => 'attribute',
        'category'  => 'core',
        'sdmx_role' => 'STRUCTURAL',
        'notes'     => 'Presentation metadata'
    ],
    [
        'id'        => 'OBS_STATUS',
        'label'     => 'Observation status',
        'type'      => 'attribute',
        'category'  => 'core',
        'sdmx_role' => 'STATUS',
        'notes'     => 'Provisional, estimated, etc.'
    ],

    // =========================
    // Common Dimensions
    // =========================

    [
        'id'        => 'FREQ',
        'label'     => 'Frequency',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'FREQ',
        'notes'     => 'A, Q, M, etc.'
    ],
    [
        'id'        => 'SEX',
        'label'     => 'Sex',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'AGE',
        'label'     => 'Age',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'AGE_GROUP',
        'label'     => 'Age group',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'EDUCATION_LEVEL',
        'label'     => 'Education level',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'OCCUPATION',
        'label'     => 'Occupation',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'ACTIVITY',
        'label'     => 'Economic activity',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'INDUSTRY',
        'label'     => 'Industry',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'SECTOR',
        'label'     => 'Institutional sector',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'COUNTERPART_AREA',
        'label'     => 'Counterpart area',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'GEO'
    ],
    [
        'id'        => 'CURRENCY',
        'label'     => 'Currency',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'PRICE_BASE',
        'label'     => 'Price base',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'CLASSIFICATION'
    ],
    [
        'id'        => 'SERIES',
        'label'     => 'Series identifier',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'SERIES'
    ],
    [
        'id'        => 'MEASURE',
        'label'     => 'Measure dimension',
        'type'      => 'dimension',
        'category'  => 'common',
        'sdmx_role' => 'STRUCTURAL',
        'notes'     => 'Used only in multi-measure datasets'
    ],

    // =========================
    // Common Attributes
    // =========================

    [
        'id'        => 'CONF_STATUS',
        'label'     => 'Confidentiality status',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'STATUS'
    ],
    [
        'id'        => 'OBS_CONF',
        'label'     => 'Observation confidentiality',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'STATUS'
    ],
    [
        'id'        => 'OBS_PRE_BREAK',
        'label'     => 'Pre-break value',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'QUALITY'
    ],
    [
        'id'        => 'OBS_POST_BREAK',
        'label'     => 'Post-break value',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'QUALITY'
    ],
    [
        'id'        => 'OBS_COM',
        'label'     => 'Observation comment',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'OTHER'
    ],
    [
        'id'        => 'OBS_FOOTNOTE',
        'label'     => 'Observation footnote',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'OTHER'
    ],
    [
        'id'        => 'DATA_QUALITY',
        'label'     => 'Data quality indicator',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'QUALITY'
    ],
    [
        'id'        => 'SOURCE',
        'label'     => 'Data source',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'OTHER'
    ],
    [
        'id'        => 'COMPILING_ORG',
        'label'     => 'Compiling organization',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'OTHER'
    ],
    [
        'id'        => 'COLLECTION',
        'label'     => 'Collection method',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'OTHER'
    ],
    [
        'id'        => 'COMMENT',
        'label'     => 'General comment',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'OTHER'
    ],
    [
        'id'        => 'ANNOTATION',
        'label'     => 'Annotation reference',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'OTHER'
    ],
    [
        'id'        => 'TIME_FORMAT',
        'label'     => 'Time format',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'STRUCTURAL'
    ],
    [
        'id'        => 'REPORTING_PERIOD',
        'label'     => 'Reporting period',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'TIME'
    ],
    [
        'id'        => 'LAST_UPDATE',
        'label'     => 'Last update date',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'TIME'
    ],
    [
        'id'        => 'RELEASE_STATUS',
        'label'     => 'Release status',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'STATUS'
    ],
    [
        'id'        => 'TIME_DETAIL',
        'label'     => 'Time detail',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'TIME'
    ],
    [
        'id'        => 'GEOGRAPHY_DETAIL',
        'label'     => 'Geography detail',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'GEO'
    ],
    [
        'id'        => 'UNIT_TYPE',
        'label'     => 'Unit type',
        'type'      => 'attribute',
        'category'  => 'common',
        'sdmx_role' => 'UNIT'
    ]

];


