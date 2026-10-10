<?php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ConsentSubmission
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $firstName = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $lastName = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $signatoryRole = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['yes', 'no'])]
    public ?string $choice = null;
}
