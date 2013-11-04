<?php namespace Enforce;

use \Exception;
use \Illuminate\Database\Eloquent;

class Model extends Eloquent\Model
{
	/**
	 * Find a model by its primary key.
	 *
	 * @param  mixed  $id
	 * @param  array  $columns
	 * @return \Illuminate\Database\Eloquent\Model|Collection|static
	 */
	public static function find($id, $columns = array('*'), $enforceOnRead=null)
	{
		$enforceOnRead = $enforceOnRead === null ? \Config::get('enforce.byDefault') : $enforceOnRead;

		$instance = new static;
		$model = $instance->newQuery()->find($id, $columns);

		return $enforceOnRead ? self::enforceOnRead($model) : $model;
	}

	/**
	 * Find a model by its primary key or throw an exception.
	 *
	 * @param  mixed  $id
	 * @param  array  $columns
	 * @return \Illuminate\Database\Eloquent\Model|Collection|static
	 */
	public static function findOrFail($id, $columns = array('*'), $enforceOnRead=null)
	{
		$this->enforceOnRead = $enforceOnRead === null ? \Config::get('enforce.byDefault') : $enforceOnRead;

		if ( ! is_null($model = static::find($id, $columns,$enforceOnRead))) return $model;

		throw new Eloquent\ModelNotFoundException;
	}

	/**
	 * Get a new query builder for the model's table.
	 *
	 * @param  bool  $excludeDeleted
	 * @return \Illuminate\Database\Eloquent\Builder|static
	 */
	public function newQuery($excludeDeleted = true)
	{
		$builder = new Builder($this->newBaseQueryBuilder());

		// Once we have the query builders, we will set the model instances so the
		// builder can easily access any information it may need from the model
		// while it is constructing and executing various queries against it.
		$builder->setModel($this)->with($this->with);

		if ($excludeDeleted and $this->softDelete)
		{
			$builder->whereNull($this->getQualifiedDeletedAtColumn());
		}

		return $builder;
	}

	/**
	 * Filter out any models supplied in which the key (set on the model) does 
	 * not match the reference value provided. Yes - this function makes use of
	 * eval... yes I have heard eval is EVIL. This method must be able to reach 
	 * deep into the bowels of a model and grab its data (e.g. 
	 * $model->primaryCompany()->locations[0]->id - or some such craziness). So 
	 * here's the thing - if you're going to allow key to be user specified - 
	 * and honestly I cannot think of a use case where this could possibly make 
	 * sense - but if you did do that then for the love of all that is not evil
	 * make sure to sanitize the input. I have no hate for eval(), it's in the 
	 * language for a reason. I do hate *pointless* use of it, but I just 
	 * couldn't think of a clean way to handle all the different cases of what 
	 * the $key might be. Do you hate eval()? Great send me the solution and 
	 * we'll change the world together!
	 * 
	 * @param $models either Illuminate\Database\Eloquent\Collection or 
	 *        Illuminate\Database\Eloquent\Model or subclass
	 * @param string $key bit of data on the model to filter against
	 * @param mixed $referenceValue value to filter model(s) against
	 * @return mixed
	 * @throws EnforceException
	 */
	protected static function enforceFilter($models, $key, $refrenceValue)
	{
        if ( is_subclass_of($models, 'Eloquent', false) )
        {
        	eval( '$modelValue = $models->'.$key.';' );

            return $modelValue == $refrenceValue ? $models : null;
        }
        elseif ( get_class($models) == 'Illuminate\Database\Eloquent\Collection' )
        {
            return $models->filter(function($model) use ($key, $refrenceValue)
            {
            	eval( '$modelValue = $models->'.$key.';' );
                return $modelValue == $refrenceValue ? $models : null;
            });
        }
        else 
        {
        	throw new EnforceException('Enforce can only filter Eloquent Models or Collections, '.get_class($models).' given.');
        }
    }

    /**
     * Default behavior (no filtering). Can be overridden in subclasses to 
     * enforce specific behavior.
     */
	public static function enforceOnRead($models) { return $models; }

	/**
	 * Default behavior (no filtering). Can be overridden in subclasses to 
	 * enforce specific behavior.
	 */
	public static function enforceOnWrite($models) { return $models; }
}