Импорт Type → ведро (маршрут размеров) для Liewood:

  php artisan lpb:seed-liewood-types /полный/путь/к/файлу.csv

Колонка: приоритет `Type`, иначе `item SubCategory`. Разделитель: `;` или `,` (по первой строке).

Дедупликация по канону (config/supplier_type_normalization.php):
  • T-SHIRT / T SHIRT / TSHIRT / «… SLIM» → одна запись TSHIRT
  • ACTIVITY TOY, CREATIVE TOYS, GARDEN GAME и др. с TOY/GAME/PUZZLE… остаются отдельно и не схлопываются с футболками

Классификация:
  • Явная карта config/liewood_type_default_buckets.php (все Type из эталонного Liewood.csv).
  • Иначе эвристики + supplier_type_normalization (игрушки → generic до regex обуви).

Опция --dry-run — только вывести без записи в БД.

Импорт LiewoodRetailTransformer сопоставляет Type с настройками по тому же канону.
