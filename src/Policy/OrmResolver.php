<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Authorization\Policy;

use Authorization\Policy\Exception\MissingPolicyException;
use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\RepositoryInterface;
use InvalidArgumentException;

/**
 * Policy resolver that applies conventions based policy classes
 * for CakePHP ORM Tables and Entities.
 */
class OrmResolver implements ResolverInterface
{
    /**
     * Application namespace.
     *
     * @var string
     */
    protected $appNamespace;

    /**
     * Constructor
     *
     * @param string $appNamespace The application namespace
     */
    public function __construct($appNamespace = 'App')
    {
        $this->appNamespace = $appNamespace;
    }

    /**
     * Get a policy for an ORM Table or Entity.
     *
     * @param \Cake\Datasource\RepositoryInterface|\Cake\Datasource\EntityInterface $resource The resource.
     * @return object
     * @throws \InvalidArgumentException When a resource is not an ORM object.
     * @throws \Authorization\Policy\Exception\MissingPolicyException When a policy for the
     *   resource has not been defined.
     */
    public function getPolicy($resource)
    {
        if ($resource instanceof EntityInterface) {
            return $this->getEntityPolicy($resource);
        }
        if ($resource instanceof RepositoryInterface) {
            return $this->getRepositoryPolicy($resource);
        }
        $type = gettype($resource);
        throw new InvalidArgumentException("Unable to resolve a policy class for '$type'.");
    }

    /**
     * Get a policy for an entity
     *
     * @param \Cake\Datasouce\EntityInterface $entity The entity to get a policy for
     * @return object
     */
    protected function getEntityPolicy(EntityInterface $entity)
    {
        $class = get_class($entity);
        $entityNamespace = '\Model\Entity\\';
        $namespace = str_replace('\\', '/', substr($class, 0, strpos($class, $entityNamespace)));
        $name = substr($class, strpos($class, $entityNamespace) + strlen($entityNamespace));

        return $this->findPolicy($class, $name, $namespace);
    }

    /**
     * Get a policy for a table
     *
     * @param \Cake\Datasouce\RepositoryInterface $table The table/repository to get a policy for.
     * @return object
     */
    protected function getRepositoryPolicy(RepositoryInterface $table)
    {
        $class = get_class($table);
        $tableNamespace = '\Model\Table\\';
        $namespace = str_replace('\\', '/', substr($class, 0, strpos($class, $tableNamespace)));
        $name = substr($class, strpos($class, $tableNamespace) + strlen($tableNamespace));

        return $this->findPolicy($class, $name, $namespace);
    }

    /**
     * Locate a policy class using conventions
     *
     * @param string $class The full class name.
     * @param string $name The name suffix of the resource.
     * @param string $namespace The namespace to find the policy in.
     * @throws \Authorization\Policy\Exception\MissingPolicyException When a policy for the
     *   resource has not been defined.
     * @return object
     */
    protected function findPolicy($class, $name, $namespace)
    {
        $policyClass = false;

        // plugin entities can have application overides defined.
        if ($namespace != $this->appNamespace) {
            $policyClass = App::className($name, 'Policy\\' . $namespace, 'Policy');
        }

        // Check the application/plugin.
        if ($policyClass === false) {
            $policyClass = App::className($namespace . '.' . $name, 'Policy', 'Policy');
        }

        if ($policyClass === false) {
            throw new MissingPolicyException([$class]);
        }

        return new $policyClass();
    }
}