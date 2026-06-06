<?php

namespace MXPOSPro\Products;

defined('ABSPATH') || exit;

class ProductSearch
{
    private ProductIndexRepository $repository;

    public function __construct(ProductIndexRepository $repository)
    {
        $this->repository = $repository;
    }

    public function search(string $query, int $limit = 20): array
    {
        $query = trim(sanitize_text_field($query));

        if (mb_strlen($query) > 100) {
            $query = mb_substr($query, 0, 100);
        }

        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 50));

        return $this->repository->search($query, $limit);
    }
}
