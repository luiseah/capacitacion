<?php

namespace Module\AcademicMobility\Core\Enums;

/**
 * What an agreement covers: training (formación), research (investigación), extension (extensión),
 * administrative (administrativa) or another activity. An agreement can cover several.
 */
enum AgreementActivity: string
{
    case Training = 'training';
    case Research = 'research';
    case Extension = 'extension';
    case Administrative = 'administrative';
    /** Anything else; the agreement says what in `other_activity`. */
    case Other = 'other';
}
