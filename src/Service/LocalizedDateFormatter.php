<?php

namespace App\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

final class LocalizedDateFormatter
{
    public function __construct(private readonly TranslatorInterface $translator) {}

    public function formatLong(\DateTimeInterface $date, string $locale): string
    {
        $locale = in_array($locale, ['fr', 'en', 'es'], true) ? $locale : 'fr';
        $month = $this->translator->trans(
            'email.date.months.' . $date->format('n'),
            [],
            'email',
            $locale,
        );

        return $this->translator->trans('email.date.long', [
            '%day%' => $date->format('j'),
            '%month%' => $month,
            '%year%' => $date->format('Y'),
        ], 'email', $locale);
    }
}
