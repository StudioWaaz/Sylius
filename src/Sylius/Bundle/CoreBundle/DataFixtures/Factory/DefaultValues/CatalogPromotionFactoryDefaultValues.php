<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Bundle\CoreBundle\DataFixtures\Factory\DefaultValues;

use Faker\Generator;
use Sylius\Bundle\CoreBundle\DataFixtures\Factory\ChannelFactoryInterface;

final class CatalogPromotionFactoryDefaultValues implements CatalogPromotionFactoryDefaultValuesInterface
{
    public function __construct(private ChannelFactoryInterface $channelFactory)
    {
    }

    public function getDefaults(Generator $faker): array
    {
        return [
            'code' => null,
            'name' => (string) $faker->words(3, true),
            'label' => null,
            'description' => $faker->sentence(),
            'channels' => $this->channelFactory::all(),
            'scopes' => [],
            'actions' => [],
            'priority' => 0,
            'exclusive' => false,
            'start_date' => null,
            'end_date' => null,
            'enabled' => true,
        ];
    }
}
