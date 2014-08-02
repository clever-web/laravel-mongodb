<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\Eloquent\Collection;
use Jenssegers\Mongodb\DatabaseManager as Resolver;
use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Query\Builder as QueryBuilder;
use Jenssegers\Mongodb\Relations\EmbedsOneOrMany;
use Jenssegers\Mongodb\Relations\EmbedsMany;
use Jenssegers\Mongodb\Relations\EmbedsOne;

use Carbon\Carbon;
use DateTime;
use MongoId;
use MongoDate;

abstract class Model extends \Jenssegers\Eloquent\Model {

    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = '_id';

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected static $resolver;

    /**
     * Custom accessor for the model's id.
     *
     * @return string
     */
    public function getIdAttribute($value)
    {
        if ($value) return (string) $value;

        // Return _id as string
        if (array_key_exists('_id', $this->attributes))
        {
            return (string) $this->attributes['_id'];
        }
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $collection
     * @return \Illuminate\Database\Eloquent\Relations\EmbedsMany
     */
    protected function embedsMany($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relatinoships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (is_null($localKey))
        {
            $localKey = $relation;
        }

        if (is_null($foreignKey))
        {
            $foreignKey = snake_case(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsMany($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $collection
     * @return \Illuminate\Database\Eloquent\Relations\EmbedsMany
     */
    protected function embedsOne($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relatinoships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (is_null($localKey))
        {
            $localKey = $relation;
        }

        if (is_null($foreignKey))
        {
            $foreignKey = snake_case(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsOne($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Convert a DateTime to a storable MongoDate object.
     *
     * @param  DateTime|int  $value
     * @return MongoDate
     */
    public function fromDateTime($value)
    {
        // If the value is already a MongoDate instance, we don't need to parse it.
        if ($value instanceof MongoDate)
        {
            return $value;
        }

        // Let Eloquent convert the value to a DateTime instance.
        if ( ! $value instanceof DateTime)
        {
            $value = parent::asDateTime($value);
        }

        return new MongoDate($value->getTimestamp());
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return DateTime
     */
    protected function asDateTime($value)
    {
        // Convert MongoDate instances.
        if ($value instanceof MongoDate)
        {
            return Carbon::createFromTimestamp($value->sec);
        }

        return parent::asDateTime($value);
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    protected function getDateFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return MongoDate
     */
    public function freshTimestamp()
    {
        return new MongoDate;
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        if (isset($this->collection)) return $this->collection;

        return parent::getTable();
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        // Check if the key is an array dot notation.
        if (strpos($key, '.') !== false)
        {
            $attributes = array_dot($this->attributes);

            if (array_key_exists($key, $attributes))
            {
                return $this->getAttributeValue($key);
            }
        }

        $camelKey = camel_case($key);

        // If the "attribute" exists as a method on the model, it may be an
        // embedded model. If so, we need to return the result before it
        // is handled by the parent method.
        if (method_exists($this, $camelKey))
        {
            $relations = $this->$camelKey();

            // This attribute matches an embedsOne or embedsMany relation so we need
            // to return the relation results instead of the interal attributes.
            if ($relations instanceof EmbedsOneOrMany)
            {
                // If the key already exists in the relationships array, it just means the
                // relationship has already been loaded, so we'll just return it out of
                // here because there is no need to query within the relations twice.
                if (array_key_exists($key, $this->relations))
                {
                    return $this->relations[$key];
                }

                // Get the relation results.
                return $this->getRelationshipFromMethod($key, $camelKey);
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        if (array_key_exists($key, $this->attributes))
        {
            return $this->attributes[$key];
        }

        else if (strpos($key, '.') !== false)
        {
            $attributes = array_dot($this->attributes);

            if (array_key_exists($key, $attributes))
            {
                return $attributes[$key];
            }
        }
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        // Convert _id to MongoId.
        if ($key == '_id' and is_string($value))
        {
            $builder = $this->newBaseQueryBuilder();
            $value = $builder->convertKey($value);
        }

        parent::setAttribute($key, $value);
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        // Because the original Eloquent never returns objects, we convert
        // MongoDB related objects to a string representation. This kind
        // of mimics the SQL behaviour so that dates are formatted
        // nicely when your models are converted to JSON.
        foreach ($attributes as $key => &$value)
        {
            if ($value instanceof MongoId)
            {
                $value = (string) $value;
            }
        }

        return $attributes;
    }

    /**
     * Remove one or more fields.
     *
     * @param  mixed $columns
     * @return int
     */
    public function drop($columns)
    {
        if ( ! is_array($columns)) $columns = array($columns);

        // Unset attributes
        foreach ($columns as $column)
        {
            $this->__unset($column);
        }

        // Perform unset only on current document
        return $this->newQuery()->where($this->getKeyName(), $this->getKey())->unset($columns);
    }

    /**
     * Append one or more values to an array.
     *
     * @return mixed
     */
    public function push()
    {
        if ($parameters = func_get_args())
        {
            $query = $this->setKeysForSaveQuery($this->newQuery());

            return call_user_func_array(array($query, 'push'), $parameters);
        }

        return parent::push();
    }

    /**
     * Remove one or more values from an array.
     *
     * @return mixed
     */
    public function pull()
    {
        $query = $this->setKeysForSaveQuery($this->newQuery());

        return call_user_func_array(array($query, 'pull'), func_get_args());
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Jenssegers\Mongodb\Query\Builder $query
     * @return \Jenssegers\Mongodb\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Unset method
        if ($method == 'unset')
        {
            return call_user_func_array(array($this, 'drop'), $parameters);
        }

        return parent::__call($method, $parameters);
    }

}
