<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\Entity;

use Schematic;

final class Entries extends SchematicEntries
{
    /**
     * @return array<string, Schematic\Entry>
     */
    public function toArray() : array
    {
        $out = parent::toArray();

        foreach ($out as $key => $entity) {
            if ($entity instanceof AEntity) {
                $out[$key] = $entity->toArray();
            }
            elseif ($entity instanceof self) {
                $out[$key] = $entity->toArray();
            }
        }

        return $out;
    }
}
