<?php

namespace Module\AcademicMobility\Core\Enums;

/**
 * How many institutions an agreement binds with the local one: a bilateral agreement is with one
 * partner institution, a multilateral one with several of them (a network, a consortium).
 */
enum AgreementParties: string
{
    case Bilateral = 'bilateral';
    case Multilateral = 'multilateral';
}
