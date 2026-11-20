<?php

// 헤더, 메뉴, D-day, 사무국 표기는 해마다 이 함수 안에서만 고친다.
// 2028 함수를 고쳐도 2027 화면은 바뀌지 않는다.

function ksers_site_profile($year) {
    $year = (int)$year;
    if ($year === 2028) {
        return ksers_site_profile_2028();
    }
    if ($year === 2029) {
        return ksers_site_profile_2029();
    }
    if ($year === 2027) {
        return ksers_site_profile_2027();
    }
    return [
        'year' => $year,
        'start' => '',
        'home' => '/' . $year,
        'news' => '/' . $year . '/en/news',
        'contact' => '/' . $year,
        'footer_title' => 'KSERS ' . $year . ' Congress Secretariat [Fm.inc]',
        'footer_lines' => [],
        'footer_email' => '',
        'menus' => [],
    ];
}

function ksers_site_profile_2027() {
    $en = '/2027/en';
    return [
        'year' => 2027,
        'start' => '2027-04-23',
        'home' => '/2027',
        'news' => '/2027/en/news',
        'contact' => '/2027',
        'footer_title' => 'KSERS 2027 Congress Secretariat [Fm.inc]',
        'footer_lines' => [
            '2F, 92, Geonji-ro 250beon-gil, Seo-gu, Incheon, Republic of Korea',
            'TEL. +82-2-2088-5945, +82-10-9116-9453',
            'FAX. +82-503-8379-6616',
        ],
        'footer_email' => 'ksers2027@ksers.kr',
        'menus' => [
            [
                'label' => 'Congress Info',
                'href' => $en . '/welcome',
                'items' => [
                    ['Welcome Message', $en . '/welcome'],
                    ['Overview', $en . '/overview'],
                    ['Committee', $en . '/committee'],
                    ['Past Congresses', $en . '/congress'],
                    ['Related Events', $en . '/related-events'],
                ],
            ],
            [
                'label' => 'Program',
                'href' => $en . '/glance',
                'items' => [
                    ['Program at a Glance', $en . '/glance'],
                    ['Master Class: Hands-On Meeting', $en . '/master-class'],
                    ['Master Class: Site Visiting Program', $en . '/site-visiting'],
                    ['Scientific Program', $en . '/scientific-program'],
                    ['Endoscopy Hands-On', $en . '/endoscopy'],
                    ['International Training', $en . '/training'],
                    ['Social Program', $en . '/social-event'],
                    ['Event', $en . '/event'],
                ],
            ],
            [
                'label' => 'Abstract',
                'href' => $en . '/submission',
                'items' => [
                    ['Abstract Submission', $en . '/submission'],
                    ['Scientific Award', $en . '/award'],
                    ['Travel Grants', $en . '/grants'],
                    ['연구비 공모', '/2027/kr/research-grant'],
                    ['Presenter Guidelines', $en . '/guidelines'],
                ],
            ],
            [
                'label' => 'Registration',
                'href' => $en . '/registration',
                'items' => [
                    ['Registration', $en . '/registration'],
                    ['Visa Assistance', $en . '/visa'],
                ],
            ],
            [
                'label' => 'Sponsorship',
                'href' => $en . '/sponsorship-oppt',
                'items' => [
                    ['Sponsorship Opportunities', $en . '/sponsorship-oppt'],
                    ['Our Sponsors', $en . '/our-sponsors'],
                ],
            ],
            [
                'label' => 'Information',
                'href' => $en . '/venue',
                'items' => [
                    ['Venue', $en . '/venue'],
                    ['Accommodation', $en . '/accommodation'],
                    ['Transportation', $en . '/transportation'],
                    ['News & Notices', $en . '/news'],
                ],
            ],
        ],
    ];
}

function ksers_site_profile_2028() {
    $en = '/2028/en';
    return [
        'year' => 2028,
        'start' => '',
        'home' => '/2028',
        'news' => '/2028/en/news',
        'contact' => '/2028',
        'footer_title' => 'KSERS 2028 Congress Secretariat [Fm.inc]',
        'footer_lines' => [
            '2F, 92, Geonji-ro 250beon-gil, Seo-gu, Incheon, Republic of Korea',
            'TEL. +82-2-2088-5945, +82-10-9116-9453',
            'FAX. +82-503-8379-6616',
        ],
        'footer_email' => 'ksers2028@ksers.kr',
        'menus' => [
            [
                'label' => 'Congress Info',
                'href' => $en . '/welcome',
                'items' => [
                    ['Welcome Message', $en . '/welcome'],
                    ['Overview', $en . '/overview'],
                    ['Committee', $en . '/committee'],
                    ['Past Congresses', $en . '/congress'],
                    ['Related Events', $en . '/related-events'],
                ],
            ],
            [
                'label' => 'Program',
                'href' => $en . '/glance',
                'items' => [
                    ['Program at a Glance', $en . '/glance'],
                    ['Master Class: Hands-On Meeting', $en . '/master-class'],
                    ['Master Class: Site Visiting Program', $en . '/site-visiting'],
                    ['Scientific Program', $en . '/scientific-program'],
                    ['Endoscopy Hands-On', $en . '/endoscopy'],
                    ['International Training', $en . '/training'],
                    ['Social Program', $en . '/social-event'],
                    ['Event', $en . '/event'],
                ],
            ],
            [
                'label' => 'Abstract',
                'href' => $en . '/submission',
                'items' => [
                    ['Abstract Submission', $en . '/submission'],
                    ['Scientific Award', $en . '/award'],
                    ['Travel Grants', $en . '/grants'],
                    ['연구비 공모', '/2028/kr/research-grant'],
                    ['Presenter Guidelines', $en . '/guidelines'],
                ],
            ],
            [
                'label' => 'Registration',
                'href' => $en . '/registration',
                'items' => [
                    ['Registration', $en . '/registration'],
                    ['Visa Assistance', $en . '/visa'],
                ],
            ],
            [
                'label' => 'Sponsorship',
                'href' => $en . '/sponsorship-oppt',
                'items' => [
                    ['Sponsorship Opportunities', $en . '/sponsorship-oppt'],
                    ['Our Sponsors', $en . '/our-sponsors'],
                ],
            ],
            [
                'label' => 'Information',
                'href' => $en . '/venue',
                'items' => [
                    ['Venue', $en . '/venue'],
                    ['Accommodation', $en . '/accommodation'],
                    ['Transportation', $en . '/transportation'],
                    ['News & Notices', $en . '/news'],
                ],
            ],
        ],
    ];
}

function ksers_site_profile_2029() {
    $en = '/2029/en';
    return [
        'year' => 2029,
        'start' => '',
        'home' => '/2029',
        'news' => '/2029/en/news',
        'contact' => '/2029',
        'footer_title' => 'KSERS 2029 Congress Secretariat [Fm.inc]',
        'footer_lines' => [
            '2F, 92, Geonji-ro 250beon-gil, Seo-gu, Incheon, Republic of Korea',
            'TEL. +82-2-2088-5945, +82-10-9116-9453',
            'FAX. +82-503-8379-6616',
        ],
        'footer_email' => 'ksers2029@ksers.kr',
        'menus' => [
            [
                'label' => 'Congress Info',
                'href' => $en . '/welcome',
                'items' => [
                    ['Welcome Message', $en . '/welcome'],
                    ['Overview', $en . '/overview'],
                    ['Committee', $en . '/committee'],
                    ['Past Congresses', $en . '/congress'],
                    ['Related Events', $en . '/related-events'],
                ],
            ],
            [
                'label' => 'Program',
                'href' => $en . '/glance',
                'items' => [
                    ['Program at a Glance', $en . '/glance'],
                    ['Master Class: Hands-On Meeting', $en . '/master-class'],
                    ['Master Class: Site Visiting Program', $en . '/site-visiting'],
                    ['Scientific Program', $en . '/scientific-program'],
                    ['Endoscopy Hands-On', $en . '/endoscopy'],
                    ['International Training', $en . '/training'],
                    ['Social Program', $en . '/social-event'],
                    ['Event', $en . '/event'],
                ],
            ],
            [
                'label' => 'Abstract',
                'href' => $en . '/submission',
                'items' => [
                    ['Abstract Submission', $en . '/submission'],
                    ['Scientific Award', $en . '/award'],
                    ['Travel Grants', $en . '/grants'],
                    ['연구비 공모', '/2029/kr/research-grant'],
                    ['Presenter Guidelines', $en . '/guidelines'],
                ],
            ],
            [
                'label' => 'Registration',
                'href' => $en . '/registration',
                'items' => [
                    ['Registration', $en . '/registration'],
                    ['Visa Assistance', $en . '/visa'],
                ],
            ],
            [
                'label' => 'Sponsorship',
                'href' => $en . '/sponsorship-oppt',
                'items' => [
                    ['Sponsorship Opportunities', $en . '/sponsorship-oppt'],
                    ['Our Sponsors', $en . '/our-sponsors'],
                ],
            ],
            [
                'label' => 'Information',
                'href' => $en . '/venue',
                'items' => [
                    ['Venue', $en . '/venue'],
                    ['Accommodation', $en . '/accommodation'],
                    ['Transportation', $en . '/transportation'],
                    ['News & Notices', $en . '/news'],
                ],
            ],
        ],
    ];
}
