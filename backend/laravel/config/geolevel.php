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

    /*
    |--------------------------------------------------------------------------
    | Outdoor Navigation (lihat docs/outdoor.md)
    |--------------------------------------------------------------------------
    */
    'outdoor' => [

        // Datum & koordinat
        'datum'                        => 'WGS84',
        'earth_radius_m'               => 6371008.8,   // IUGG mean radius (meter), model bola untuk haversine
        'utm_zone_min'                 => 46,
        'utm_zone_max'                 => 54,
        'utm_central_meridian_easting' => 500000,
        'utm_false_northing_south'     => 10000000,
        'coordinate_formats'           => ['dd', 'dms', 'utm', 'mgrs'],
        'default_coordinate_format'    => 'dd',

        // Perekaman track (client)
        'record_min_distance_m'       => 5,
        'record_min_interval_s'       => 2,
        'record_max_accuracy_m'       => 30,
        'record_auto_pause_speed_mps' => 0.3,
        'record_auto_pause_after_s'   => 30,
        'record_batch_size'           => 30,
        'record_batch_interval_s'     => 15,
        'record_use_wake_lock'        => true,

        // Batas data
        'track_max_points'      => 100000,
        'gpx_max_size_mb'       => 10,
        'gpx_max_waypoints'     => 500,
        'route_max_vertices'    => 500,
        'waypoint_max_per_user' => 5000,
        'waypoint_categories'   => ['generic', 'survey_marker', 'camp', 'water', 'danger', 'summit', 'parking'],

        // Statistik & penyederhanaan
        'stats_elevation_noise_threshold_m' => 3.0,
        'stats_moving_speed_min_mps'        => 0.3,
        'simplify_tolerance_display_m'      => 2,
        'simplify_tolerance_ai_m'           => 10,

        // Peta & offline
        'tile_cache_max_mb'        => 200,
        'tile_cache_ttl_days'      => 30,
        'offline_queue_max_points' => 20000,

        // Ekspor
        'export_disk' => 'local',
        'export_path' => 'exports/outdoor',
    ],

];