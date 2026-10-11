<?php
namespace App\Service;

use App\Dto\ConsentSubmission;
use App\Entity\Client;

final class ConsentPrefill
{
    /**
     * L'identité du client est un texte libre : cette répartition reste une
     * proposition modifiable, jamais une vérification du signataire.
     */
    public static function forClient(Client $client): ConsentSubmission
    {
        $submission = new ConsentSubmission();
        $parts = preg_split('/\s+/u', trim($client->getName()), 2);

        if (is_array($parts) && count($parts) === 2
            && mb_strlen($parts[0]) <= 120 && mb_strlen($parts[1]) <= 120) {
            [$submission->firstName, $submission->lastName] = $parts;
        }

        return $submission;
    }
}
