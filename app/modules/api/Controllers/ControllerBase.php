<?php
/*
 * sysPass
 *
 * @author nuxsmin
 * @link https://syspass.org
 * @copyright 2012-2020, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Modules\Api\Controllers;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Klein\Klein;
use League\Fractal\Manager;
use Psr\Container\ContainerInterface;
use SP\Config\ConfigData;
use SP\Core\Acl\Acl;
use SP\Core\Context\StatelessContext;
use SP\Core\Events\EventDispatcher;
use SP\Core\Exceptions\SPException;
use SP\Http\Json;
use SP\Services\Api\ApiResponse;
use SP\Services\Api\ApiService;
use SP\Services\Api\JsonRpcResponse;
use SP\Services\ServiceException;

/**
 * Class ControllerBase
 *
 * @package SP\Modules\Api\Controllers
 */
abstract class ControllerBase
{
    const SEARCH_COUNT_ITEMS = 25;
    /**
     * @var ContainerInterface
     */
    protected $dic;
    /**
     * @var string
     */
    protected $controllerName;
    /**
     * @var
     */
    protected $actionName;
    /**
     * @var StatelessContext
     */
    protected $context;
    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;
    /**
     * @var ApiService
     */
    protected $apiService;
    /**
     * @var Klein
     */
    protected $router;
    /**
     * @var ConfigData
     */
    protected $configData;
    /**
     * @var Manager
     */
    protected $fractal;
    /**
     * @var Acl
     */
    protected $acl;
    /**
     * @var bool
     */
    private $isAuthenticated = false;

    /**
     * Constructor
     *
     * @param Container $container
     * @param string    $actionName
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    public final function __construct(Container $container, string $actionName)
    {
        $this->dic = $container;
        $this->context = $container->get(StatelessContext::class);
        $this->configData = $container->get(ConfigData::class);
        $this->eventDispatcher = $container->get(EventDispatcher::class);
        $this->router = $container->get(Klein::class);
        $this->apiService = $container->get(ApiService::class);
        $this->acl = $container->get(Acl::class);
        $this->fractal = new Manager();

        $this->controllerName = $this->getControllerName();
        $this->actionName = $actionName;

        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }
    }

    /**
     * @return string
     */
    final protected function getControllerName(): string
    {
        $class = static::class;

        return substr($class, strrpos($class, '\\') + 1, -strlen('Controller')) ?: '';
    }

    /**
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        return $this->isAuthenticated;
    }

    /**
     * @param int $actionId
     *
     * @throws SPException
     * @throws ServiceException
     */
    final protected function setupApi(int $actionId)
    {
        $this->apiService->setup($actionId);

        $this->isAuthenticated = true;
    }

    /**
     * Devuelve una respuesta en formato JSON con el estado y el mensaje.
     *
     * {"jsonrpc": "2.0", "result": 19, "id": 3}
     *
     * @param ApiResponse $apiResponse
     */
    final protected function returnResponse(ApiResponse $apiResponse)
    {
        try {
            if ($this->isAuthenticated === false) {
                throw new SPException(__u('Unauthorized access'));
            }

            $this->sendJsonResponse(JsonRpcResponse::getResponse($apiResponse, $this->apiService->getRequestId()));
        } catch (SPException $e) {
            processException($e);

            $this->returnResponseException($e);
        }
    }

    /**
     * Returns a JSON response back to the browser
     *
     * @param string $response
     */
    final private function sendJsonResponse(string $response)
    {
        $json = Json::factory($this->router->response());
        $json->returnRawJson($response);
    }

    /**
     * @param Exception $e
     */
    final protected function returnResponseException(Exception $e)
    {
        $this->sendJsonResponse(JsonRpcResponse::getResponseException($e, $this->apiService->getRequestId()));
    }
}