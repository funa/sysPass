<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2018, Rubén Domínguez nuxsmin@$syspass.org
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

namespace SP\Services\Crypt;

use SP\Core\Crypt\Crypt;
use SP\Core\Crypt\Hash;
use SP\Core\Crypt\Session;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\DataModel\Dto\ConfigRequest;
use SP\Repositories\NoSuchItemException;
use SP\Services\Config\ConfigService;
use SP\Services\Service;
use SP\Services\ServiceException;
use SP\Util\Util;

/**
 * Class TemporaryMasterPassService
 *
 * @package SP\Services\Crypt
 */
class TemporaryMasterPassService extends Service
{
    /**
     * Número máximo de intentos
     */
    const MAX_ATTEMPTS = 50;
    /**
     * @var ConfigService
     */
    protected $configService;
    /**
     * @var int
     */
    protected $maxTime;

    /**
     * Crea una clave temporal para encriptar la clave maestra y guardarla.
     *
     * @param int $maxTime El tiempo máximo de validez de la clave
     *
     * @return string
     * @throws ServiceException
     */
    public function create($maxTime = 14400)
    {
        try {
            $this->maxTime = time() + $maxTime;

            // Encriptar la clave maestra con hash aleatorio generado
            $randomKey = Util::generateRandomBytes(32);
            $secureKey = Crypt::makeSecuredKey($randomKey);

            $configRequest = new ConfigRequest();
            $configRequest->add('tempmaster_pass', Crypt::encrypt(Session::getSessionKey($this->context), $secureKey, $randomKey));
            $configRequest->add('tempmaster_passkey', $secureKey);
            $configRequest->add('tempmaster_passhash', Hash::hashKey($randomKey));
            $configRequest->add('tempmaster_passtime', time());
            $configRequest->add('tempmaster_maxtime', $this->maxTime);
            $configRequest->add('tempmaster_attempts', 0);

            $this->configService->saveBatch($configRequest);

            // Guardar la clave temporal hasta que finalice la sesión
            $this->context->setTemporaryMasterPass($randomKey);

            $this->eventDispatcher->notifyEvent('create.tempMasterPassword',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Generar Clave Temporal')))
            );

            return $randomKey;
        } catch (\Exception $e) {
            processException($e);

            throw new ServiceException(__u('Error al generar clave temporal'));
        }
    }

    /**
     * Comprueba si la clave temporal es válida
     *
     * @param string $pass clave a comprobar
     *
     * @return bool
     * @throws ServiceException
     */
    public function checkTempMasterPass($pass)
    {
        try {
            $isValid = false;
            $passTime = (int)$this->configService->getByParam('tempmaster_passtime');
            $passMaxTime = (int)$this->configService->getByParam('tempmaster_maxtime');
            $attempts = (int)$this->configService->getByParam('tempmaster_attempts');

            // Comprobar si el tiempo de validez o los intentos se han superado
            if ($passMaxTime === 0) {
                $this->eventDispatcher->notifyEvent('check.tempMasterPassword',
                    new Event($this, EventMessage::factory()->addDescription(__u('Clave temporal caducada')))
                );

                return $isValid;
            }

            if ((!empty($passTime) && time() > $passMaxTime)
                || $attempts >= self::MAX_ATTEMPTS
            ) {
                $this->expire();

                return $isValid;
            }

            $isValid = Hash::checkHashKey($pass, $this->configService->getByParam('tempmaster_passhash'));

            if (!$isValid) {
                $this->configService->save('tempmaster_attempts', $attempts + 1);
            }

            return $isValid;
        } catch (NoSuchItemException $e) {
            return false;
        } catch (\Exception $e) {
            processException($e);

            throw new ServiceException(__u('Error al comprobar clave temporal'));
        }
    }

    /**
     * @throws ServiceException
     */
    protected function expire()
    {
        $configRequest = new ConfigRequest();
        $configRequest->add('tempmaster_pass', '');
        $configRequest->add('tempmaster_passkey', '');
        $configRequest->add('tempmaster_passhash', '');
        $configRequest->add('tempmaster_passtime', 0);
        $configRequest->add('tempmaster_maxtime', 0);
        $configRequest->add('tempmaster_attempts', 0);

        $this->configService->saveBatch($configRequest);

        $this->eventDispatcher->notifyEvent('expire.tempMasterPassword',
            new Event($this, EventMessage::factory()
                ->addDescription(__u('Clave temporal caducada')))
        );
    }

    /**
     * Devuelve la clave maestra que ha sido encriptada con la clave temporal
     *
     * @param $key string con la clave utilizada para encriptar
     *
     * @return string con la clave maestra desencriptada
     * @throws NoSuchItemException
     * @throws ServiceException
     * @throws \Defuse\Crypto\Exception\CryptoException
     */
    public function getUsingKey($key)
    {
        return Crypt::decrypt($this->configService->getByParam('tempmaster_pass'),
            $this->configService->getByParam('tempmaster_passkey'),
            $key);
    }

    /**
     * @return int
     */
    public function getMaxTime()
    {
        return $this->maxTime;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function initialize()
    {
        $this->configService = $this->dic->get(ConfigService::class);
    }
}