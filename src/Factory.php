<?php

namespace League\FactoryMuffin;

use Exception;
use Faker\Factory as Faker;
use League\FactoryMuffin\Exceptions\DeleteFailedException;
use League\FactoryMuffin\Exceptions\DeleteMethodNotFoundException;
use League\FactoryMuffin\Exceptions\DeletingFailedException;
use League\FactoryMuffin\Exceptions\DirectoryNotFoundException;
use League\FactoryMuffin\Exceptions\NoDefinedFactoryException;
use League\FactoryMuffin\Exceptions\SaveFailedException;
use League\FactoryMuffin\Exceptions\SaveMethodNotFoundException;
use League\FactoryMuffin\Exceptions\ClassNotFoundException;
use League\FactoryMuffin\Generators\Base as Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

/**
 * This is the factory class.
 *
 * This class is not intended to be used directly, but should be used through
 * the provided facade. The only time where you should be directly calling
 * methods here should be when you're using method chaining after initially
 * using the facade.
 *
 * @package League\FactoryMuffin
 * @author  Zizaco <zizaco@gmail.com>
 * @author  Scott Robertson <scottymeuk@gmail.com>
 * @author  Graham Campbell <graham@mineuk.com>
 * @license <https://github.com/thephpleague/factory-muffin/blob/master/LICENSE> MIT
 */
class Factory
{
    /**
     * The array of factories.
     *
     * @var array
     */
    private $factories = array();

    /**
     * The array of objects we have created.
     *
     * @var array
     */
    private $saved = array();

    /**
     * This is the method used when saving objects.
     *
     * @var string
     */
    private $saveMethod = 'save';

    /**
     * This is the method used when deleting objects.
     *
     * @var string
     */
    private $deleteMethod = 'delete';

    /**
     * The faker instance.
     *
     * @var \Faker\Generator
     */
    private $faker;

    /**
     * The faker localization.
     *
     * @var string
     */
    private $fakerLocale = 'en_EN';

    /**
     * Set the faker locale.
     *
     * @param string $local The faker locale.
     *
     * @return $this
     */
    public function setFakerLocale($local)
    {
        $this->fakerLocale = $local;

        // The faker class must be instantiated again with a new the new locale
        $this->faker = null;

        return $this;
    }

    /**
     * Set the method we use when saving objects.
     *
     * @param string $method The save method name.
     *
     * @return $this
     */
    public function setSaveMethod($method)
    {
        $this->saveMethod = $method;

        return $this;
    }

    /**
     * Set the method we use when deleting objects.
     *
     * @param string $method The delete method name.
     *
     * @return $this
     */
    public function setDeleteMethod($method)
    {
        $this->deleteMethod = $method;

        return $this;
    }

    /**
     * Returns multiple versions of an object.
     *
     * These objects are generated by the create function, so are saved to the
     * database.
     *
     * @param int    $times The number of models to create.
     * @param string $model The model class name.
     * @param array  $attr  The model attributes.
     *
     * @return object[]
     */
    public function seed($times, $model, array $attr = array())
    {
        $seeds = array();
        while ($times > 0) {
            $seeds[] = $this->create($model, $attr);
            $times--;
        }

        return $seeds;
    }

    /**
     * Creates and saves in db an instance of the model.
     *
     * This object will be generated with mock attributes.
     *
     * @param string $model The model class name.
     * @param array  $attr  The model attributes.
     *
     * @throws \League\FactoryMuffin\Exceptions\SaveFailedException
     *
     * @return object
     */
    public function create($model, array $attr = array())
    {
        $obj = $this->make($model, $attr, true);

        if (!$this->save($obj)) {
            if (isset($obj->validationErrors) && $obj->validationErrors) {
                throw new SaveFailedException($model, $obj->validationErrors);
            }

            throw new SaveFailedException($model);
        }

        return $obj;
    }

    /**
     * Make an instance of the model.
     *
     * @param string $model The model class name.
     * @param array  $attr  The model attributes.
     * @param bool   $save  Are we saving, or just creating an instance?
     *
     * @return object
     */
    private function make($model, array $attr, $save)
    {
        $group = $this->getGroup($model);
        $modelWithoutGroup = $this->getModelWithoutGroup($model);

        if (! class_exists($modelWithoutGroup)) {
            throw new ClassNotFoundException($modelWithoutGroup);
        }

        $obj = new $modelWithoutGroup();

        if ($save) {
            $this->saved[] = $obj;
        }

        // Get the factory attributes for that model
        if ($group) {
            $attr = array_merge($attr, $this->getFactoryAttrs($model));
        }

        $attributes = $this->attributesFor($obj, $attr);

        foreach ($attributes as $attr => $value) {
            $obj->$attr = $value;
        }

        return $obj;
    }

    /**
     * Returns the group name for this factory defintion
     *
     * @param  string $model
     * @return string
     */
    private function getGroup($model)
    {
        return current(explode(':', $model));
    }

    /**
     * Returns the model without the group prefix
     *
     * @param  string $model
     * @return string
     */
    private function getModelWithoutGroup($model)
    {
        return str_replace($this->getGroup($model) . ':', null, $model);
    }

    /**
     * Save our object to the db, and keep track of it.
     *
     * @param object $object The model instance.
     *
     * @throws \League\FactoryMuffin\Exceptions\SaveMethodNotFoundException
     *
     * @return mixed
     */
    private function save($object)
    {
        if (method_exists($object, $method = $this->saveMethod)) {
            return $object->$method();
        }

        throw new SaveMethodNotFoundException($object, $method);
    }

    /**
     * Return an array of saved objects.
     *
     * @return object[]
     */
    public function saved()
    {
        return $this->saved;
    }

    /**
     * Is the object saved?
     *
     * @param object $object The model instance.
     *
     * @return bool
     */
    public function isSaved($object)
    {
        return in_array($object, $this->saved, true);
    }

    /**
     * Call the delete method on any saved objects.
     *
     * @throws \League\FactoryMuffin\Exceptions\DeletingFailedException
     *
     * return $this
     */
    public function deleteSaved()
    {
        $exceptions = array();
        foreach ($this->saved() as $object) {
            try {
                if (!$this->delete($object)) {
                    throw new DeleteFailedException(get_class($object));
                }
            } catch (Exception $e) {
                $exceptions[] = $e;
            }
        }

        // Flush the saved models list
        $this->saved = array();

        // If we ran into problem, throw the exception now
        if ($exceptions) {
            throw new DeletingFailedException($exceptions);
        }

        return $this;
    }

    /**
     * Delete our object from the db.
     *
     * @param object $object The model instance.
     *
     * @throws \League\FactoryMuffin\Exceptions\DeleteMethodNotFoundException
     *
     * @return mixed
     */
    private function delete($object)
    {
        if (method_exists($object, $method = $this->deleteMethod)) {
            return $object->$method();
        }

        throw new DeleteMethodNotFoundException($object, $method);
    }

    /**
     * Return an instance of the model.
     *
     * This does not save it in the database. Use create for that.
     *
     * @param string $model The model class name.
     * @param array  $attr  The model attributes.
     *
     * @return object
     */
    public function instance($model, array $attr = array())
    {
        return $this->make($model, $attr, false);
    }

    /**
     * Returns the mock attributes for the model.
     *
     * @param object $object The model instance.
     * @param array  $attr   The model attributes.
     *
     * @return array
     */
    public function attributesFor($object, array $attr = array())
    {
        $factory_attrs = $this->getFactoryAttrs(get_class($object));
        $attributes = array_merge($factory_attrs, $attr);

        // Prepare attributes
        foreach ($attributes as $key => $kind) {
            $attr[$key] = $this->generateAttr($kind, $object);
        }

        return $attr;
    }

    /**
     * Get factory attributes.
     *
     * @param string $model The model class name.
     *
     * @throws \League\FactoryMuffin\Exceptions\NoDefinedFactoryException
     *
     * @return array
     */
    private function getFactoryAttrs($model)
    {
        if (isset($this->factories[$model])) {
            return $this->factories[$model];
        }

        throw new NoDefinedFactoryException($model);
    }

    /**
     * Define a new model factory.
     *
     * @param string $model      The model class name.
     * @param array  $definition The attribute definitions.
     *
     * @return $this
     */
    public function define($model, array $definition = array())
    {
        $this->factories[$model] = $definition;

        return $this;
    }

    /**
     * Generate the attributes.
     *
     * This method will return a string, or an instance of the model.
     *
     * @param string      $kind   The kind of attribute.
     * @param object|null $object The model instance.
     *
     * @return string|object
     */
    public function generateAttr($kind, $object = null)
    {
        $kind = Generator::detect($kind, $object, $this->getFaker());

        return $kind->generate();
    }

    /**
     * Get the faker instance.
     *
     * @return \Faker\Generator
     */
    private function getFaker()
    {
        if (!$this->faker) {
            $this->faker = Faker::create($this->fakerLocale);
        }

        return $this->faker;
    }

    /**
     * Load the specified factories.
     *
     * This method expects either a single path to a directory containing php
     * files, or an array of directory paths, and will include_once every file.
     * These files should contain factory definitions for your models.
     *
     * @param string|string[] $paths The directory path(s) to load.
     *
     * @throws \League\FactoryMuffin\Exceptions\DirectoryNotFoundException
     *
     * @return $this
     */
    public function loadFactories($paths)
    {
        foreach ((array) $paths as $path) {
            if (!is_dir($path)) {
                throw new DirectoryNotFoundException($path);
            }

            $this->loadDirectory($path);
        }

        return $this;
    }

    /**
     * Load all the files in a directory.
     *
     * @param string $path The directory path to load.
     *
     * @return void
     */
    private function loadDirectory($path)
    {
        $directory = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);
        $files = new RegexIterator($iterator, '/^.+\.php$/i');

        foreach ($files as $file) {
            include_once $file->getPathName();
        }
    }
}
