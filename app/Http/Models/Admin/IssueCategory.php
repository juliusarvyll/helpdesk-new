<?php

namespace App\Http\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class IssueCategory extends Model
{
    protected $table = 'issue_category';

    public static function generate_colors($number)
    {
        switch ($number) {
            case '0':
                return 'red';
                break;
            case '1':
                return 'yellow';
                break;
            case '2':
                return 'aqua';
                break;
            case '3':
                return 'blue';
                break;
            case '4':
                return 'teal';
                break;

            case '5':
                return 'light-blue';
                break;

            case '5':
                return 'green';
                break;

            case '6':
                return 'gray';
                break;

            case '7':
                return 'navy';
                break;

            case '8':
                return 'olive';
                break;

            case '9':
                return 'lime';
                break;

            case '10':
                return 'orange';
                break;

            case '11':
                return 'fuchsia';
                break;
            case '12':
                return 'purple';
                break;
            case '13':
                return 'maroon';
                break;
            case '14':
                return 'black';
                break;
            default:
                return 'white';
                // code...
                break;
        }
    }
}
