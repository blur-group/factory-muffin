<?php

namespace League\FactoryMuffin\Generators;

use InvalidArgumentException;

/**
 * This is the generic generator class.
 *
 * The generic generator will be the generator you use the most. It will
 * communicate with the faker library in order to generate your attribute.
 * Please note that class is not be considered part of the public api, and
 * should only be used internally by Factory Muffin.
 *
 * @package League\FactoryMuffin\Generators
 * @author  Zizaco <zizaco@gmail.com>
 * @author  Scott Robertson <scottymeuk@gmail.com>
 * @author  Graham Campbell <graham@mineuk.com>
 * @license <https://github.com/thephpleague/factory-muffin/blob/master/LICENSE> MIT
 */
final class Arrayparam extends Generic
{
    /**
     * Initialise our Generator.
     *
     * @param string                $kind   The kind of attribute
     * @param object|null           $object The model instance.
     * @param \Faker\Generator|null $faker  The faker instance.
     *
     * @return void
     */
    public function __construct($kind, $object = null, $faker = null)
    {
        $prefix = 'arrayparam|';
        // drop $prefix from the start of kind...
        if (strpos($kind, $prefix) !== 0) {
            throw new \Exception('Unable to process the arr generator attribute as it does not start with '.$prefix);
        }

        $kind = substr($kind, strlen($prefix));
        parent::__construct($kind, $object, $faker);
    }


    /**
     * Return an array of all options passed to the Generator (after |).
     *
     * @return array
     */
    public function getOptions()
    {
        $options = explode('|', $this->kind);
        array_shift($options);

        $delimiter = array_shift($options);

        if (count($options) > 0) {
            $options = explode(';', $options[0]);
            $newOptions = [];
            foreach ($options as $option) {
                if (isset($delimiter) && strpos($option, $delimiter) !== FALSE) {
                    $newOptions[] = explode($delimiter, $option);
                } else {
                    $newOptions[] = $option;
                }
            }
        }

        return $newOptions;
    }


}
