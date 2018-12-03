<?php
//
//namespace Univer\Tests\Entities;
//
//use Univer\Entities\Category;
//use Univer\Tests\AbstractTestCase;
//
//class CategoryTest extends AbstractTestCase
//{
//    public function setUp()
//    {
//        parent::setUp();
//        $this->migrate();
//    }
//
//    public function test_create_model()
//    {
//        $category = Category::create(['name' => "Category Test", 'active' => true]);
//        $this->assertEquals('Category Test', $category->name);
//
//        $category = Category::all()->first();
//        $this->assertEquals('Category Test', $category->name);
//
//    }
//
//    public function test_check_if_can_assign_a_parent_to_a_category()
//    {
//        $parentCategory = Category::create(['name' => 'Parent Test', 'active' => true]);
//
//        $category = Category::create(['name' => 'Category Test', 'active' => true]);
//
//        $category->parent()->associate($parentCategory)->save();
//
//        $child = $parentCategory->children->first();
//
//            $this->assertEquals('Category Test', $child->name);
//        $this->assertEquals('Parent Test', $category->parent->name);
//    }
//
//}