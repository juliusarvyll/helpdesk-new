<?php

namespace App\Http\Models;

use Auth;
use Config;
use Illuminate\Database\Eloquent\Model;

class MenuController extends Model
{
    public static function menu_controller()
    {

        if (Auth::check()) {

            switch (Auth::user()->role) {
                case 'admin':

                    $count_active = Ticket::count_active(null);
                    $technical_assigment_count_active = Ticket::technical_assigment_count_active(Auth::id());

                    $menu[] = 'DASHBOARD';
                    $menu[] = [
                        'text' => 'OVERVIEW',
                        'url' => 'admin/overview',
                        'icon' => 'home',
                        'can' => 'admin',
                    ];

                    $menu[] =
                              [
                                  'text' => 'Dashboard',
                                  'url' => 'admin/dashboard',
                                  'icon' => 'dashboard',
                              ];

                    $menu[] = 'ACCOUNT SETTINGS';
                    $menu[] = [
                        'text' => 'Profile',
                        'url' => 'profile',
                        'icon' => 'user',

                    ];
                    $menu[] = 'MANAGE TICKETS';

                    if ($count_active == 0) {

                        $menu[] = [
                            'text' => 'MANAGE TICKETS',
                            'url' => 'admin/tickets',
                            'icon' => 'database',
                            'can' => 'admin',
                        ];

                    } else {
                        $menu[] = [
                            'text' => 'MANAGE TICKETS',
                            'url' => 'admin/tickets',
                            'icon' => 'database',
                            'can' => 'admin',
                            'label' => $count_active,
                            'label_color' => 'warning',
                        ];

                    }

                    if ($technical_assigment_count_active == 0) {
                        $menu[] = [
                            'text' => 'ASSIGNED TICKETS',
                            'url' => 'admin/assigned/tickets',
                            'icon' => 'tasks',
                            'can' => 'admin',
                        ];
                    } else {
                        $menu[] = [
                            'text' => 'ASSIGNED TICKETS',
                            'url' => 'admin/assigned/tickets',
                            'icon' => 'tasks',
                            'can' => 'admin',
                            'label' => $technical_assigment_count_active,
                            'label_color' => 'warning',
                        ];
                    }

                    $menu[] = [
                        'text' => 'TICKETS',
                        'url' => 'ticket',
                        'icon' => 'ticket',
                    ];
                    $menu[] = 'MANAGE SYSTEM';
                    $menu[] = [
                        'text' => 'Accounts',
                        'url' => 'admin/accounts',
                        'icon' => 'users',
                        'can' => 'admin',
                    ];

                    $menu[] =
                      [
                          'text' => 'Department',
                          'url' => 'admin/department',
                          'icon' => 'building',
                          'can' => 'admin',
                      ];
                    $menu[] =
                      [
                          'text' => 'Position',
                          'url' => 'admin/position',
                          'icon' => 'black-tie',
                          'can' => 'admin',
                      ];
                    $menu[] =
                      [
                          'text' => 'ISSUE CATEGORY',
                          'url' => 'admin/issue/category',
                          'icon' => 'gears ',
                          'can' => 'admin',
                      ];
                    $menu[] =
                      [
                          'text' => 'ISSUE LIST',
                          'url' => 'admin/list/issue',
                          'icon' => 'list',
                          'can' => 'admin',
                      ];
                    // $menu[] =
                    //   [
                    //       'text'        => 'THEMES',
                    //       'url'         => 'themes',
                    //       'icon'        => 'gear',
                    //   ];
                    $menu[] = 'ACTIVITY LOGS';
                    $menu[] =

                      [
                          'text' => 'Activity Logs',
                          'url' => 'activity',
                          'icon' => 'history',
                      ];
                    $menu[] = 'REPORTS';

                    // $menu[] =
                    //  [
                    //      'text'        => 'CREATE REPORTS',
                    //      'url'         => 'admin/reports/create',
                    //      'icon'        => 'line-chart',
                    //      'can'         => 'admin',
                    //  ];

                    $menu[] =
                      [
                          'text' => 'REPORTS',
                          'icon' => 'line-chart',
                          'can' => 'admin',
                          'submenu' => [

                              [
                                  'text' => 'By Ticket',
                                  'icon' => 'caret-right',
                                  'url' => 'admin/reports/single/ticket',
                              ],
                              [
                                  'text' => 'Overall',
                                  'icon' => 'caret-right',
                                  'url' => 'admin/reports/overall',
                              ],
                              [
                                  'text' => 'Department',
                                  'icon' => 'caret-right',
                                  'url' => 'admin/reports/department',
                              ],
                              [
                                  'text' => 'Status',
                                  'icon' => 'caret-right',
                                  'url' => 'admin/reports/status',
                              ],
                              [
                                  'text' => 'Category',
                                  'icon' => 'caret-right',
                                  'url' => 'admin/reports/category',
                              ],
                              [
                                  'text' => 'Issue',
                                  'icon' => 'caret-right',
                                  'url' => 'admin/reports/issue',
                              ],

                              [
                                  'text' => 'Technical Support',
                                  'icon' => 'caret-right',
                                  'url' => 'admin/reports/technical_support',
                              ],
                              [
                                  'text' => 'Ratings',
                                  'icon' => 'caret-right',
                                  'url' => 'admin/reports/ratings',
                              ],
                          ],
                      ];

                    break;

                case 'client':

                    $count_active = Ticket::count_active(Auth::id());

                    $menu[] = 'DASHBOARD';
                    $menu[] =
                              [
                                  'text' => 'Dashboard',
                                  'url' => 'admin/dashboard',
                                  'icon' => 'dashboard',
                              ];

                    $menu[] = 'ACCOUNT SETTINGS';
                    $menu[] = [
                        'text' => 'Profile',
                        'url' => 'profile',
                        'icon' => 'user',

                    ];
                    $menu[] = 'MANAGE TICKETS';

                    if ($count_active == 0) {

                        $menu[] = [
                            'text' => 'TICKETS',
                            'url' => 'ticket',
                            'icon' => 'ticket',
                        ];

                    } else {
                        $menu[] = [
                            'text' => 'TICKETS',
                            'url' => 'ticket',
                            'icon' => 'ticket',
                            'label' => $count_active,
                            'label_color' => 'success',
                        ];

                    }

                    // $menu[] =  'MANAGE SYSTEM';

                    // $menu[] =
                    //   [
                    //       'text'        => 'THEMES',
                    //       'url'         => 'themes',
                    //       'icon'        => 'gear',
                    //   ];
                    $menu[] = 'ACTIVITY LOGS';
                    $menu[] =

                      [
                          'text' => 'Activity Logs',
                          'url' => 'activity',
                          'icon' => 'history',
                      ];
                    $menu[] = 'REPORTS';

                    $menu[] =
                      [
                          'text' => 'CREATE REPORTS',
                          'url' => 'reports/create',
                          'icon' => 'line-chart',
                          'can' => 'client',
                      ];

                    break;

                default:

                    break;
            }

            Config::set('adminlte.menu', $menu);

        }

    }
}
