<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

abstract class BaseModel extends Model
{
    /**
     * Convenient alias for insert().
     *
     * @param array<string, mixed> $data
     * @return int|string
     */
    public function create(array $data): int|string
    {
        return $this->insert($data);
    }

    /**
     * Convenient alias for softDelete() / delete.
     */
    public function delete(int|string $id): bool
    {
        return $this->softDelete($id);
    }
}

