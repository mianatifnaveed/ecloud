<?php

namespace App\Solution;

/**
 * Class EncryptionBillingType
 *
 * Billing type model for VM encryption.
 *
 * @package App\Solution
 */
class EncryptionBillingType
{
    /**
     * Contract.
     * The solution has VM encryption enabled contract, allowing any/all VM's to be encrypted/decrypted at will.
     */
    const CONTRACT = 'Contract';

    /**
     * PAYG
     * The solution has VM encryption enabled using a pay-as-you-go Credit model
     */
    const PAYG = 'PAYG';
}
