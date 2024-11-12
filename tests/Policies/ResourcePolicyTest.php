<?php

namespace StatamicRadPack\Runway\Tests\Policies;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use StatamicRadPack\Runway\Runway;
use StatamicRadPack\Runway\Tests\TestCase;
use Workbench\App\Models\Post;

class ResourcePolicyTest extends TestCase
{
    #[Test]
    public function can_view_resource()
    {
        $resource = Runway::findResource('post');

        Role::make('test')->addPermission('view post')->save();
        $user = User::make()->assignRole('test')->save();

        $this->assertTrue($user->can('view', $resource));
    }

    #[Test]
    public function can_view_resource_with_model()
    {
        $resource = Runway::findResource('post');

        Role::make('test')->addPermission('view post')->save();
        $user = User::make()->assignRole('test')->save();

        $this->assertTrue($user->can('view', [$resource, new Post]));
    }

    #[Test]
    public function can_create_resource()
    {
        $resource = Runway::findResource('post');

        Role::make('test')->addPermission('create post')->save();
        $user = User::make()->assignRole('test')->save();

        $this->assertTrue($user->can('create', $resource));
    }

    public function can_edit_resource()
    {
        $resource = Runway::findResource('post');

        Role::make('test')->addPermission('edit post')->save();
        $user = User::make()->assignRole('test')->save();

        $this->assertTrue($user->can('edit', $resource));
    }

    #[Test]
    public function can_edit_resource_with_model()
    {
        $resource = Runway::findResource('post');

        Role::make('test')->addPermission('edit post')->save();
        $user = User::make()->assignRole('test')->save();

        $this->assertTrue($user->can('edit', [$resource, new Post]));
    }

    public function can_delete_resource()
    {
        $resource = Runway::findResource('post');

        Role::make('test')->addPermission('delete post')->save();
        $user = User::make()->assignRole('test')->save();

        $this->assertTrue($user->can('delete', $resource));
    }

    #[Test]
    public function can_delete_resource_with_model()
    {
        $resource = Runway::findResource('post');

        Role::make('test')->addPermission('delete post')->save();
        $user = User::make()->assignRole('test')->save();

        $this->assertTrue($user->can('delete', [$resource, new Post]));
    }
}
