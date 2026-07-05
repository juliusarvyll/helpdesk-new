<?php

namespace App;

enum PreventiveMaintenanceSessionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
