<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BT Deviation Limit
    | Validasi: |bt_field - bt_computed| <= nilai ini → valid
    | bt_computed = (ba + bb) / 2
    |--------------------------------------------------------------------------
    */
    'bt_deviation_limit' => 0.002,

    /*
    |--------------------------------------------------------------------------
    | Tolerance Classes
    | Nilai dalam meter — digunakan untuk menghitung allowed_tolerance
    | Formula: fh_allowed = constant * sqrt(distance_km)
    |--------------------------------------------------------------------------
    */
    'tolerance_classes' => [
        'LAA' => 0.002,
        'LA'  => 0.004,  // default
        'LB'  => 0.008,
        'LC'  => 0.012,
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'default_tolerance_class'   => 'LA',
    'default_adjustment_method' => 'equal',

    /*
     * PERUBAHAN: Tambah 'reset' ke daftar method yang valid.
     * AdjustProjectRequest mengambil daftar ini via config('geolevel.adjustment_methods')
     * untuk validasi rule 'in:...'. Tanpa 'reset' di sini, test yang mengirim
     * method='reset' akan kena 422 karena dianggap method tidak valid.
     *
     * 'reset' bukan metode perataan matematis — fungsinya mengembalikan semua
     * correction ke 0 dan adjusted_elevation ke raw_elevation.
     */
    'adjustment_methods' => ['equal', 'bowditch', 'least_squares', 'reset'],

    /*
    |--------------------------------------------------------------------------
    | Precision Policy
    | Sesuai database.md — gunakan bcmath untuk semua kalkulasi elevasi
    |--------------------------------------------------------------------------
    */
    'precision' => [
        'elevation'  => 4,  // NUMERIC(12,4)
        'correction' => 6,  // NUMERIC(12,6)
        'distance'   => 3,  // NUMERIC(10,3)
    ],

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    */
    'export_disk'    => 'local',
    'export_path'    => 'exports',
    'default_paper'  => 'a4',

    /*
    |--------------------------------------------------------------------------
    | Queue Timeouts (detik)
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'recalculate_timeout' => 60,
        'export_timeout'      => 120,
    ],

];