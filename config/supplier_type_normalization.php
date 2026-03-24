<?php

/**
 * Нормализация Type/категорий поставщика для маршрута размеров и дедупликации в настройках.
 *
 * Важно: составные типы вроде ACTIVITY TOY (игрушка по слову TOY) не схлопываются
 * с футболками; t-shirt / t shirt / tshirt — в один канонический ключ.
 */
return [
    /**
     * Якорные слова в Type (часто второе слово вроде … TOY / … GAME / … INSTRUMENT):
     * считаем не-одежду, не схлопываем с футболками и не режем хвосты как у apparel.
     * Покрытие сверено с Liewood.csv + типичные детские линейки.
     *
     * Исключение: если есть токен из apparel_override_tokens (shirt, swim, shorts…),
     * защита снимается (напр. SWIM TEE остаётся одеждой).
     */
    'toy_play_tokens' => [
        // Игрушки / игра
        'TOY',
        'TOYS',
        'PUZZLE',
        'DOLL',
        'DOLLS',
        'FIGURINE',
        'GAME',
        'GAMES',
        'PLAYSET',
        'RATTLE',
        'PLUSH',
        'TEDDY',
        // Творчество / обучение
        'CRAYON',
        'CRAYONS',
        'STICKER',
        'STICKERS',
        'BOOK',
        'BOOKS',
        // Музыка / звук (MUSIC INSTRUMENT)
        'INSTRUMENT',
        // Вода / двор / активность (GARDEN GAME, POOLS)
        'POOL',
        'POOLS',
        // Аксессуары не-одежда
        'KEYCHAIN',
        'EYEWEAR',
        'BAG',
        'BAGS',
        'TRAVELBAG',
        'BACKPACK',
        // Транспорт игрушечный (часто отдельный Type у брендов)
        'SCOOTER',
        'BICYCLE',
        'WAGON',
        'TRIKE',
        'TRICYCLE',
    ],

    /**
     * Наличие этих токенов (слово) снимает toy-защиту — лицензионная футболка и т.п.
     */
    'apparel_override_tokens' => [
        'SHIRT',
        'TEE',
        'TOP',
        'TIGHTS',
        'LEGGINGS',
        'SOCKS',
        'SWEAT',
        'HOODIE',
        'JACKET',
        'PANTS',
        'DRESS',
        'JUMPER',
        'CARDIGAN',
        'BLOUSE',
        'BODYSUIT',
        'SWIM',
        'BIKINI',
        'SHORTS',
        'SKIRT',
        'TROUSERS',
    ],

    /**
     * Хвосты, которые отрезаем для дедупа (одинаковый ведро для размера), только в apparel-ветке.
     * Длинные фразы первыми.
     */
    'trailing_modifiers' => [
        'LOOSE FIT',
        'SLIM FIT',
        'SLIM',
        'REGULAR FIT',
        'LONG SLEEVE',
        'SHORT SLEEVE',
        'CROPPED',
        'OVERSIZED',
        'ROUND NECK',
        'V NECK',
        'LOOSE',
    ],

    /**
     * После очистки хвостов: если строка начинается с одного из префиксов — заменяем на canonical.
     */
    'apparel_prefix_groups' => [
        [
            'canonical' => 'TSHIRT',
            'prefixes' => [
                'T SHIRT',
                'TSHIRT',
                'T-SHIRT',
                'TEE SHIRT',
                'TEE',
            ],
        ],
    ],
];
