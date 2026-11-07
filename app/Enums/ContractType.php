<?php

namespace App\Enums;

enum ContractType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Adjunct = 'adjunct';
}
