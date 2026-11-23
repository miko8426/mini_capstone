<?php

declare(strict_types=1);

/**
 * Route table: HTTP method => [uri pattern => Controller@method].
 * {id} segments are matched as positive integers and passed to the action.
 */
return [
    'GET' => [
        '/' => 'HomeController@index',
        '/home' => 'HomeController@index',
        '/dashboard' => 'DashboardController@index',
        '/login' => 'AuthController@showLogin',
        '/logout' => 'AuthController@logout',

        /* Organization */
        '/campuses' => 'CampusController@index',
        '/offices' => 'OfficeController@index',

        /* Appliance inventory */
        '/appliance-categories' => 'ApplianceCategoryController@index',
        '/appliances' => 'ApplianceController@index',
        '/appliances/create' => 'ApplianceController@create',
        '/appliances/import' => 'ApplianceController@import',
        '/appliances/import/template.csv' => 'ApplianceController@templateCsv',
        '/appliances/{id}' => 'ApplianceController@show',
        '/appliances/{id}/edit' => 'ApplianceController@edit',

        /* Usage, history, analytics */
        '/usage' => 'UsageController@index',
        '/history' => 'HistoryController@index',
        '/history/{id}' => 'HistoryController@show',
        '/analytics' => 'AnalyticsController@index',

        /* Rates, factors, thresholds, notifications */
        '/rates' => 'ElectricityRateController@index',
        '/emission-factors' => 'EmissionFactorController@index',
        '/thresholds' => 'ThresholdController@index',
        '/notifications' => 'NotificationController@index',

        /* Reports, users, audit */
        '/reports' => 'ReportController@index',
        '/reports/generate' => 'ReportController@generate',
        '/users' => 'UserController@index',
        '/audit-logs' => 'AuditLogController@index',
        '/recycle-bin' => 'RecycleBinController@index',
    ],
    'POST' => [
        '/login' => 'AuthController@login',
        '/login-post' => 'AuthController@login',

        /* Organization */
        '/campuses' => 'CampusController@store',
        '/campuses/store' => 'CampusController@store',
        '/campuses/{id}/update' => 'CampusController@update',
        '/campuses/{id}/delete' => 'CampusController@delete',

        '/offices' => 'OfficeController@store',
        '/offices/{id}/update' => 'OfficeController@update',
        '/offices/{id}/delete' => 'OfficeController@delete',

        /* Appliance inventory */
        '/appliance-categories' => 'ApplianceCategoryController@store',
        '/appliance-categories/{id}/update' => 'ApplianceCategoryController@update',
        '/appliance-categories/{id}/delete' => 'ApplianceCategoryController@delete',

        '/appliances' => 'ApplianceController@store',
        '/appliances/import' => 'ApplianceController@previewImport',
        '/appliances/import/confirm' => 'ApplianceController@confirmImport',
        '/appliances/{id}/update' => 'ApplianceController@update',
        '/appliances/{id}/status' => 'ApplianceController@updateStatus',
        '/appliances/bulk-delete' => 'ApplianceController@bulkDelete',
        '/appliances/{id}/archive' => 'ApplianceController@archive',
        '/appliances/{id}/restore' => 'ApplianceController@restore',

        /* Usage */
        '/usage' => 'UsageController@store',

        /* Rates and factors */
        '/rates' => 'ElectricityRateController@store',
        '/rates/{id}/update' => 'ElectricityRateController@update',
        '/rates/{id}/delete' => 'ElectricityRateController@delete',

        '/emission-factors' => 'EmissionFactorController@store',
        '/emission-factors/{id}/update' => 'EmissionFactorController@update',
        '/emission-factors/{id}/delete' => 'EmissionFactorController@delete',

        /* Thresholds and notifications */
        '/thresholds' => 'ThresholdController@store',
        '/thresholds/evaluate' => 'ThresholdController@evaluate',
        '/thresholds/{id}/update' => 'ThresholdController@update',
        '/thresholds/{id}/delete' => 'ThresholdController@delete',
        '/notifications/read-all' => 'NotificationController@markAllRead',
        '/notifications/{id}/read' => 'NotificationController@markRead',

        /* Users */
        '/users' => 'UserController@store',
        '/users/{id}/update' => 'UserController@update',
        '/users/{id}/status' => 'UserController@setStatus',
        '/users/{id}/delete' => 'UserController@destroy',

        /* Recycle bin */
        '/recycle-bin/users/bulk-purge' => 'RecycleBinController@purgeUsers',
        '/recycle-bin/appliances/bulk-purge' => 'RecycleBinController@purgeAppliances',
        '/recycle-bin/users/{id}/restore' => 'RecycleBinController@restoreUser',
        '/recycle-bin/users/{id}/purge' => 'RecycleBinController@purgeUser',
        '/recycle-bin/appliances/{id}/restore' => 'RecycleBinController@restoreAppliance',
        '/recycle-bin/appliances/{id}/purge' => 'RecycleBinController@purgeAppliance',
    ],
];
