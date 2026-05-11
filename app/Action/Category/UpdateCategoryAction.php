<?php

declare(strict_types=1);

namespace App\Action\Category;

use App\DTO\Category\UpdateCategoryInput;
use App\DTO\Category\UpdateCategoryOutput;
use App\Models\Category;
use App\Support\Logging\FormatsLogMessage;
use DomainException;
use Psr\Log\LoggerInterface;

class UpdateCategoryAction
{
    use FormatsLogMessage;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(UpdateCategoryInput $input): UpdateCategoryOutput
    {
        $this->logger->info($this->formatLogMessage('started'), [
            'user_id' => $input->userId,
            'category_id' => $input->id,
            'name' => $input->name,
        ]);

        $category = Category::query()->findOrFail($input->id);

        if ($category->user_id !== $input->userId) {
            $this->logger->warning($this->formatLogMessage('forbidden update attempt'), [
                'user_id' => $input->userId,
                'category_id' => $input->id,
                'owner_id' => $category->user_id,
            ]);
            throw new DomainException('Você não pode alterar esta categoria.');
        }

        if (Category::query()
            ->where('user_id', $input->userId)
            ->where('name', $input->name)
            ->where('id', '!=', $input->id)
            ->exists()) {
            $this->logger->warning($this->formatLogMessage('duplicate category'), [
                'user_id' => $input->userId,
                'name' => $input->name,
            ]);
            throw new DomainException('Categoria já registrada.');
        }

        $category->update([
            'name' => $input->name,
            'color' => $input->color,
        ]);

        $this->logger->info($this->formatLogMessage('completed'), [
            'user_id' => $input->userId,
            'category_id' => $category->id,
        ]);

        return new UpdateCategoryOutput('Categoria atualizada com sucesso.');
    }
}
