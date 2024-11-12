<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Workbench\App\Models\Author;
use Workbench\App\Models\Post;

class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $title = implode(' ', $this->faker->words(6)),
            'slug' => Str::slug($title),
            'body' => implode(' ', $this->faker->paragraphs(10)),
            'author_id' => Author::factory()->create()->id,
            'published' => true,
            'mutated_value' => 'Foo',
        ];
    }

    public function unpublished()
    {
        return $this->state([
            'published' => false,
        ]);
    }
}
