<?php
declare(strict_types=1);

namespace Trejjam\MailChimp\Entity;

use Schematic;

class SchematicEntries extends Schematic\Entries
{
    /**
     * Schematic calls array_key_exists() with a null key at the end of the iteration, which PHP 8.5 deprecates.
     */
    public function valid() : bool
    {
        return $this->key() !== null;
    }
}
