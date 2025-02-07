<?php

namespace StatamicRadPack\Runway\Tests\Console\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Spatie\TestTime\TestTime;
use SplFileInfo;
use Statamic\Facades\Blueprint;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Tests\TestCase;
use StatamicRadPack\Runway\Traits\HasRunwayResource;

class GenerateMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('runway', [
            'resources' => [
                Food::class => ['handle' => 'food'],
                Drink::class => ['handle' => 'drink'],
            ],
        ]);

        Runway::discoverResources();

        collect(File::allFiles(database_path('migrations')))->each(function (SplFileInfo $file) {
            File::delete($file->getRealPath());
        });
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        collect(File::allFiles(database_path('migrations')))->each(function (SplFileInfo $file) {
            File::delete($file->getRealPath());
        });
    }

    #[Test]
    public function can_generate_migrations_for_multiple_resources()
    {
        TestTime::freeze();

        $postBlueprint = Blueprint::find('runway::post');
        $authorBlueprint = Blueprint::find('runway::author');

        $foodBlueprint = Blueprint::makeFromFields([
            'name' => ['type' => 'text', 'validate' => 'required'],
            'metadata->calories' => ['type' => 'integer', 'validate' => 'required'],
        ]);

        $drinkBlueprint = Blueprint::makeFromFields([
            'name' => ['type' => 'text', 'validate' => 'required'],
            'metadata->calories' => ['type' => 'integer', 'validate' => 'required'],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn($authorBlueprint);
        Blueprint::shouldReceive('find')->with('runway::food')->andReturn($foodBlueprint);
        Blueprint::shouldReceive('find')->with('runway::drink')->andReturn($drinkBlueprint);

        $this
            ->artisan('runway:generate-migrations')
            ->expectsQuestion('Should we run your migrations?', false)
            ->execute();

        $this->assertFileExists(
            $expectedFoodsMigrationPath = database_path().'/migrations/'.now()->format('Y_m_d_His').'_create_foods_table.php'
        );

        $this->assertStringContainsString('return new class extends Migration', File::get($expectedFoodsMigrationPath));
        $this->assertStringContainsString("Schema::create('foods', function (Blueprint", File::get($expectedFoodsMigrationPath));
        $this->assertStringContainsString('$table->text(\'name\')', File::get($expectedFoodsMigrationPath));
        $this->assertStringContainsString('$table->json(\'metadata\')', File::get($expectedFoodsMigrationPath));

        $this->assertFileExists(
            $expectedDrinksMigrationPath = database_path().'/migrations/'.now()->format('Y_m_d_His').'_create_drinks_table.php'
        );

        $this->assertStringContainsString('return new class extends Migration', File::get($expectedDrinksMigrationPath));
        $this->assertStringContainsString("Schema::create('drinks', function (Blueprint", File::get($expectedDrinksMigrationPath));
        $this->assertStringContainsString('$table->text(\'name\')', File::get($expectedDrinksMigrationPath));
        $this->assertStringContainsString('$table->json(\'metadata\')', File::get($expectedDrinksMigrationPath));

        $this->assertFalse(Schema::hasTable('foods'));
        $this->assertFalse(Schema::hasTable('drinks'));
    }

    #[Test]
    public function can_generate_migration_for_single_resource()
    {
        TestTime::freeze();

        $postBlueprint = Blueprint::find('runway::post');
        $authorBlueprint = Blueprint::find('runway::author');

        $foodBlueprint = Blueprint::makeFromFields([
            'name' => ['type' => 'text', 'validate' => 'required'],
            'metadata->calories' => ['type' => 'integer', 'validate' => 'required'],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn($authorBlueprint);
        Blueprint::shouldReceive('find')->with('runway::food')->andReturn($foodBlueprint);

        Schema::shouldReceive('hasTable')
            ->with('foods')
            ->andReturn(false);

        Schema::shouldReceive('dropIfExists');

        $this
            ->artisan('runway:generate-migrations', [
                'resource' => 'food',
            ])
            ->expectsQuestion('Should we run your migrations?', false)
            ->execute();

        $this->assertFileExists(
            $expectedMigrationPath = database_path().'/migrations/'.now()->format('Y_m_d_His').'_create_foods_table.php'
        );

        $this->assertStringContainsString('return new class extends Migration', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->text(\'name\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->json(\'metadata\')', File::get($expectedMigrationPath));

        $this->assertFalse(Schema::hasTable('foods'));
    }

    #[Test]
    public function cant_generate_migration_where_table_already_exists()
    {
        TestTime::freeze();

        $postBlueprint = Blueprint::find('runway::post');
        $authorBlueprint = Blueprint::find('runway::author');

        $foodBlueprint = Blueprint::makeFromFields([
            'name' => ['type' => 'text', 'validate' => 'required'],
            'metadata->calories' => ['type' => 'integer', 'validate' => 'required'],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn($authorBlueprint);
        Blueprint::shouldReceive('find')->with('runway::food')->andReturn($foodBlueprint);

        Schema::shouldReceive('hasTable')
            ->with('foods')
            ->andReturn(true);

        Schema::shouldReceive('dropIfExists');

        $this
            ->artisan('runway:generate-migrations', [
                'resource' => 'food',
            ])
            ->expectsQuestion('Should we run your migrations?', false)
            ->expectsOutput('Table [foods] already exists. Runway is not smart enough yet to update existing migrations. Sorry!')
            ->execute();

        $this->assertFileDoesNotExist(database_path().'/migrations/'.now()->format('Y_m_d_His').'_create_foods_table.php');
    }

    #[Test]
    public function can_generate_migration_and_run_them_afterwards()
    {
        TestTime::freeze();

        $postBlueprint = Blueprint::find('runway::post');
        $authorBlueprint = Blueprint::find('runway::author');

        $foodBlueprint = Blueprint::makeFromFields([
            'name' => ['type' => 'text', 'validate' => 'required'],
            'metadata->calories' => ['type' => 'integer', 'validate' => 'required'],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn($authorBlueprint);
        Blueprint::shouldReceive('find')->with('runway::food')->andReturn($foodBlueprint);

        $this->assertFalse(Schema::hasTable('foods'));

        $this
            ->artisan('runway:generate-migrations', [
                'resource' => 'food',
            ])
            ->expectsQuestion('Should we run your migrations?', true)
            ->execute();

        $this->assertFileExists(database_path().'/migrations/'.now()->format('Y_m_d_His').'_create_foods_table.php');

        $this->assertTrue(Schema::hasTable('foods'));
    }

    #[Test]
    public function can_generate_migration_and_ensure_normal_field_is_correct()
    {
        TestTime::freeze();

        $postBlueprint = Blueprint::find('runway::post');
        $authorBlueprint = Blueprint::find('runway::author');

        $foodBlueprint = Blueprint::makeFromFields([
            'ramond_the_array' => ['type' => 'array'],
            'the_big_red_button' => ['type' => 'button_group'],
            'floating_away' => ['type' => 'float'],
            'int_the_ant' => ['type' => 'integer'],
            'toggle_me_smth' => ['type' => 'toggle'],
            'author_id' => ['type' => 'belongs_to'],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn($authorBlueprint);
        Blueprint::shouldReceive('find')->with('runway::food')->andReturn($foodBlueprint);

        $this
            ->artisan('runway:generate-migrations', [
                'resource' => 'food',
            ])
            ->expectsQuestion('Should we run your migrations?', false)
            ->execute();

        $this->assertFileExists(
            $expectedMigrationPath = database_path().'/migrations/'.now()->format('Y_m_d_His').'_create_foods_table.php'
        );

        $this->assertStringContainsString('return new class extends Migration', File::get($expectedMigrationPath));

        $this->assertStringContainsString('$table->json(\'ramond_the_array\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->string(\'the_big_red_button\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->float(\'floating_away\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->integer(\'int_the_ant\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->boolean(\'toggle_me_smth\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->bigInteger(\'author_id\')', File::get($expectedMigrationPath));

        $this->assertFalse(Schema::hasTable('foods'));
    }

    #[Test]
    public function can_generate_migration_and_ensure_max_items_1_field_is_correct()
    {
        TestTime::freeze();

        $postBlueprint = Blueprint::find('runway::post');
        $authorBlueprint = Blueprint::find('runway::author');

        $foodBlueprint = Blueprint::makeFromFields([
            'assets' => ['type' => 'assets'],
            'collections' => ['type' => 'collections'],
            'entries' => ['type' => 'entries'],
            'terms' => ['type' => 'terms'],
            'users' => ['type' => 'users'],

            'asset' => ['type' => 'assets', 'max_items' => 1],
            'collection' => ['type' => 'collections', 'max_items' => 1],
            'entry' => ['type' => 'entries', 'max_items' => 1],
            'term' => ['type' => 'terms', 'max_items' => 1],
            'user' => ['type' => 'users', 'max_items' => 1],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn($authorBlueprint);
        Blueprint::shouldReceive('find')->with('runway::food')->andReturn($foodBlueprint);

        $this
            ->artisan('runway:generate-migrations', [
                'resource' => 'food',
            ])
            ->expectsQuestion('Should we run your migrations?', false)
            ->execute();

        $this->assertFileExists(
            $expectedMigrationPath = database_path().'/migrations/'.now()->format('Y_m_d_His').'_create_foods_table.php'
        );

        $this->assertStringContainsString('return new class extends Migration', File::get($expectedMigrationPath));

        $this->assertStringContainsString('$table->json(\'assets\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->json(\'collections\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->json(\'entries\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->json(\'terms\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->json(\'users\')', File::get($expectedMigrationPath));

        $this->assertStringContainsString('$table->string(\'asset\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->string(\'collection\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->string(\'entry\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->string(\'term\')', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->string(\'user\')', File::get($expectedMigrationPath));

        $this->assertFalse(Schema::hasTable('foods'));
    }

    #[Test]
    public function can_generate_migration_and_ensure_field_is_nullable_if_required_not_set()
    {
        TestTime::freeze();

        $postBlueprint = Blueprint::find('runway::post');
        $authorBlueprint = Blueprint::find('runway::author');

        $foodBlueprint = Blueprint::makeFromFields([
            'text' => ['type' => 'text'],
            'entries' => ['type' => 'entries'],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn($authorBlueprint);
        Blueprint::shouldReceive('find')->with('runway::food')->andReturn($foodBlueprint);

        $this
            ->artisan('runway:generate-migrations', [
                'resource' => 'food',
            ])
            ->expectsQuestion('Should we run your migrations?', false)
            ->execute();

        $this->assertFileExists(
            $expectedMigrationPath = database_path().'/migrations/'.now()->format('Y_m_d_His').'_create_foods_table.php'
        );

        $this->assertStringContainsString('return new class extends Migration', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->text(\'text\')->nullable();', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->json(\'entries\')->nullable();', File::get($expectedMigrationPath));

        $this->assertFalse(Schema::hasTable('foods'));
    }

    #[Test]
    public function can_generate_migration_and_ensure_field_is_not_nullable_if_required_set()
    {
        TestTime::freeze();

        $postBlueprint = Blueprint::find('runway::post');
        $authorBlueprint = Blueprint::find('runway::author');

        $foodBlueprint = Blueprint::makeFromFields([
            'text' => ['type' => 'text', 'validate' => 'required'],
            'entries' => ['type' => 'entries', 'validate' => 'required'],
        ]);

        Blueprint::shouldReceive('find')->with('runway::post')->andReturn($postBlueprint);
        Blueprint::shouldReceive('find')->with('runway::author')->andReturn($authorBlueprint);
        Blueprint::shouldReceive('find')->with('runway::food')->andReturn($foodBlueprint);

        $this
            ->artisan('runway:generate-migrations', [
                'resource' => 'food',
            ])
            ->expectsQuestion('Should we run your migrations?', false)
            ->execute();

        $this->assertFileExists(
            $expectedMigrationPath = database_path().'/migrations/'.now()->format('Y_m_d_His').'_create_foods_table.php'
        );

        $this->assertStringContainsString('return new class extends Migration', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->text(\'text\');', File::get($expectedMigrationPath));
        $this->assertStringContainsString('$table->json(\'entries\');', File::get($expectedMigrationPath));

        $this->assertFalse(Schema::hasTable('foods'));
    }
}

class Food extends Model
{
    use HasRunwayResource;

    protected $table = 'foods';

    protected $fillable = ['name'];
}

class Drink extends Model
{
    use HasRunwayResource;

    protected $table = 'drinks';

    protected $fillable = ['name'];
}
