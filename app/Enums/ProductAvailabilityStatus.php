<?php

namespace App\Enums;

enum ProductAvailabilityStatus: string
{
    case Available = 'available';
    case OutOfStock = 'out_of_stock';
}
