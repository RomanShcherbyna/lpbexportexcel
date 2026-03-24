<?php

/**
 * Явные вёдра для Type Liewood (обувь / шапка / носки / generic).
 * Сверено с уникальными Type в LPB/resources/mappings/Liewood.csv.
 *
 * Ключ — верхний регистр; можно дублировать канон после нормализации (напр. HATS/CAP и HATS CAP).
 * Сидер ищет: точное совпадение строки Type, затем canonicalForRouting(), затем эвристики.
 */
return [
    'map' => [
        // Игрушки, игра, аксессуары, одежда (кроме обуви/шапок)
        'ACTIVITY TOY' => 'generic',
        'BIKINI' => 'generic',
        'BOARD SHORTS' => 'generic',
        'CREATIVE TOYS' => 'generic',
        'DRESS' => 'generic',
        'DUNGAREE' => 'generic',
        'EYEWEAR' => 'generic',
        'GARDEN GAME' => 'generic',
        'KEYCHAIN' => 'generic',
        'MUSIC INSTRUMENT' => 'generic',
        'OVERSHIRT' => 'generic',
        'PANTS' => 'generic',
        'POOLS' => 'generic',
        'SET' => 'generic',
        'SHORTS' => 'generic',
        'SKIRT' => 'generic',
        'SWEATSHIRT' => 'generic',
        'SWIM TEE' => 'generic',
        'SWIM VEST' => 'generic',
        'SWIMPANTS' => 'generic',
        'SWIMSUIT' => 'generic',
        'TEDDY' => 'generic',
        'TRAVELBAG' => 'generic',
        'TSHIRT' => 'generic',

        // Обувь
        'SANDALS' => 'footwear',
        'SNEAKERS' => 'footwear',
        'SWIM SHOE' => 'footwear',

        // Шапки / кепки
        'HATS/CAP' => 'hat',
        'HATS CAP' => 'hat',
    ],
];
