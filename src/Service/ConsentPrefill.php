<?php
namespace App\Service;

use App\Dto\ConsentSubmission;
use App\Entity\Client;

final class ConsentPrefill
{
    /**
     * Never infer a signatory's name from the company's trade name.
     * The optional client contact is only a suggestion, not proof of identity.
     */
    public static function forClient(Client $client): ConsentSubmission
    {
        $submission = new ConsentSubmission();
        $submission->firstName = $client->getContactFirstName() ?? '';
        $submission->lastName = $client->getContactLastName() ?? '';
        $submission->signatoryRole = $client->getContactRole() ?? '';

        return $submission;
    }
}
