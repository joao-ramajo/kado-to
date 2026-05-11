<?php

declare(strict_types=1);

namespace App\Action\Category;

use App\DTO\Category\CreateCategoryInput;
use App\DTO\Category\CreateCategoryOutput;
use App\Models\Category;
use App\Support\Logging\FormatsLogMessage;
use DomainException;
use Psr\Log\LoggerInterface;

class CreateCategoryAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(CreateCategoryInput $input): CreateCategoryOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
            'name' => $input->name,
        ]);

        if (Category::whereName($input->name)->whereUserId($input->userId)->exists()) {
            $this->logger->warning($this->formatLogMessage('duplicate category'), [
                'user_id' => $input->userId,
                'name' => $input->name,
            ]);
            throw new DomainException('Categoria já registrada.');
        }

        $category = Category::create([
            'name' => $input->name,
            'user_id' => $input->userId,
            'color' => $input->color,
        ]);

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'category_id' => $category->id,
        ]);

        return new CreateCategoryOutput('Categoria registrada com sucesso.');
    }
}
