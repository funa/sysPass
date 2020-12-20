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

namespace SP\Core\Context;

use SP\DataModel\Dto\AccountCache;
use SP\DataModel\ProfileData;
use SP\Services\User\UserLoginResponse;

/**
 * Class ContextInterface
 *
 * @package SP\Core\Session
 */
interface ContextInterface
{
    /**
     * @throws ContextException
     */
    public function initialize();

    /**
     * Establecer la hora de carga de la configuración
     *
     * @param int $time
     */
    public function setConfigTime(int $time);

    /**
     * Devolver la hora de carga de la configuración
     *
     * @return int
     */
    public function getConfigTime(): int;

    /**
     * Establece los datos del usuario en la sesión.
     *
     * @param UserLoginResponse|null $userLoginResponse
     */
    public function setUserData(?UserLoginResponse $userLoginResponse = null);

    /**
     * Obtiene el objeto de perfil de usuario de la sesión.
     *
     * @return ProfileData|null
     */
    public function getUserProfile(): ?ProfileData;

    /**
     * Establece el objeto de perfil de usuario en la sesión.
     *
     * @param ProfileData $ProfileData
     */
    public function setUserProfile(ProfileData $ProfileData);

    /**
     * Returns if user is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool;

    /**
     * Devuelve los datos del usuario en la sesión.
     *
     * @return UserLoginResponse|null
     */
    public function getUserData(): ?UserLoginResponse;

    /**
     * Establecer el lenguaje de la sesión
     *
     * @param $locale
     */
    public function setLocale($locale);

    /**
     * Devuelve el lenguaje de la sesión
     *
     * @return string|null
     */
    public function getLocale(): ?string;

    /**
     * Devuelve el estado de la aplicación
     *
     * @return bool|null
     */
    public function getAppStatus(): ?bool;

    /**
     * Establecer el estado de la aplicación
     *
     * @param string $status
     */
    public function setAppStatus(string $status);

    /**
     * Reset del estado de la aplicación
     *
     * @return bool|null
     */
    public function resetAppStatus(): ?bool;

    /**
     * @return AccountCache[]|null
     */
    public function getAccountsCache(): ?array;

    /**
     * Sets an arbitrary key in the trasient collection.
     * This key is not bound to any known method or type
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     * @throws ContextException
     */
    public function setTrasientKey(string $key, $value);

    /**
     * Gets an arbitrary key from the trasient collection.
     * This key is not bound to any known method or type
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getTrasientKey(string $key, $default = null);

    /**
     * Sets a temporary master password
     *
     * @param string $password
     */
    public function setTemporaryMasterPass(string $password);

    /**
     * @param string $pluginName
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function setPluginKey(string $pluginName, string $key, $value);

    /**
     * @param string $pluginName
     * @param string $key
     *
     * @return mixed
     */
    public function getPluginKey(string $pluginName, string $key);
}