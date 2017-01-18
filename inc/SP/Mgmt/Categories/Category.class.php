<?php

/**
 * sysPass
 *
 * @author nuxsmin
 * @link http://syspass.org
 * @copyright 2012-2017, Rubén Domínguez nuxsmin@$syspass.org
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

namespace SP\Mgmt\Categories;

defined('APP_ROOT') || die();

use SP\Core\ActionsInterface;
use SP\Core\Exceptions\SPException;
use SP\DataModel\CategoryData;
use SP\DataModel\CustomFieldData;
use SP\Log\Log;
use SP\Mgmt\CustomFields\CustomField;
use SP\Mgmt\ItemInterface;
use SP\Mgmt\ItemSelectInterface;
use SP\Mgmt\ItemTrait;
use SP\Storage\DB;
use SP\Storage\QueryData;


/**
 * Esta clase es la encargada de realizar las operaciones sobre las categorías de sysPass.
 */
class Category extends CategoryBase implements ItemInterface, ItemSelectInterface
{
    use ItemTrait;

    /**
     * @return $this
     * @throws SPException
     */
    public function add()
    {
        if ($this->checkDuplicatedOnAdd()) {
            throw new SPException(SPException::SP_WARNING, __('Categoría duplicada', false));
        }

        $query = /** @lang SQL */
            'INSERT INTO categories SET category_name = ?, category_description = ?, category_hash = ?';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($this->itemData->getCategoryName());
        $Data->addParam($this->itemData->getCategoryDescription());
        $Data->addParam($this->makeItemHash($this->itemData->getCategoryName()));

        if (DB::getQuery($Data) === false) {
            throw new SPException(SPException::SP_CRITICAL, __('Error al crear la categoría', false));
        }

        $this->itemData->setCategoryId(DB::$lastId);

        return $this;
    }

    /**
     * Comprobar duplicados
     *
     * @return bool
     * @throws \SP\Core\Exceptions\SPException
     */
    public function checkDuplicatedOnAdd()
    {
        $query = /** @lang SQL */
            'SELECT category_id FROM categories WHERE category_hash = ? OR category_name = ?';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($this->makeItemHash($this->itemData->getCategoryName()));
        $Data->addParam($this->itemData->getCategoryName());

        $queryRes = DB::getResults($Data);

        if ($queryRes !== false) {
            if ($Data->getQueryNumRows() === 0) {
                return false;
            } elseif ($Data->getQueryNumRows() === 1) {
                $this->itemData->setCategoryId($queryRes->category_id);
            }
        }

        return true;
    }

    /**
     * @param $id int|array
     * @return mixed
     * @throws \SP\Core\Exceptions\InvalidClassException
     * @throws \SP\Core\Exceptions\SPException
     */
    public function delete($id)
    {
        if (is_array($id)) {
            foreach ($id as $itemId) {
                $this->delete($itemId);
            }

            return $this;
        }

        if ($this->checkInUse($id)) {
            throw new SPException(SPException::SP_WARNING, __('No es posible eliminar', false));
        }

        // FIXME: utilizar SQL
        $oldCategory = $this->getById($id);

        if (!is_object($oldCategory)) {
            throw new SPException(SPException::SP_CRITICAL, __('Categoría no encontrada', false));
        }

        $query = /** @lang SQL */
            'DELETE FROM categories WHERE category_id = ? LIMIT 1';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($id);

        if (DB::getQuery($Data) === false) {
            throw new SPException(SPException::SP_CRITICAL, __('Error al eliminar la categoría', false));
        }

        try {
            $CustomFieldData = new CustomFieldData();
            $CustomFieldData->setModule(ActionsInterface::ACTION_MGM_CATEGORIES);
            CustomField::getItem($CustomFieldData)->delete($id);
        } catch (SPException $e) {
            Log::writeNewLog(__FUNCTION__, $e->getMessage(), Log::ERROR);
        }

        return $this;
    }

    /**
     * @param $id int
     * @return mixed
     */
    public function checkInUse($id)
    {
        $query = /** @lang SQL */
            'SELECT account_id FROM accounts WHERE account_categoryId = ?';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($id);

        DB::getQuery($Data);

        return $Data->getQueryNumRows() > 0;
    }

    /**
     * @param $id int
     * @return CategoryData
     */
    public function getById($id)
    {
        $query = /** @lang SQL */
            'SELECT category_id, category_name, category_description FROM categories WHERE category_id = ? LIMIT 1';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($id);
        $Data->setMapClassName($this->getDataModel());

        return DB::getResults($Data);
    }

    /**
     * @return $this
     * @throws \SP\Core\Exceptions\SPException
     */
    public function update()
    {
        if ($this->checkDuplicatedOnUpdate()) {
            throw new SPException(SPException::SP_WARNING, __('Nombre de categoría duplicado', false));
        }

        $query = /** @lang SQL */
            'UPDATE categories
              SET category_name = ?,
              category_description = ?,
              category_hash = ?
              WHERE category_id = ? LIMIT 1';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($this->itemData->getCategoryName());
        $Data->addParam($this->itemData->getCategoryDescription());
        $Data->addParam($this->makeItemHash($this->itemData->getCategoryName()));
        $Data->addParam($this->itemData->getCategoryId());

        if (DB::getQuery($Data) === false) {
            throw new SPException(SPException::SP_CRITICAL, __('Error al actualizar la categoría', false));
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function checkDuplicatedOnUpdate()
    {
        $query = /** @lang SQL */
            'SELECT category_id FROM categories WHERE (category_hash = ? OR category_name = ?) AND category_id <> ?';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($this->makeItemHash($this->itemData->getCategoryName()));
        $Data->addParam($this->itemData->getCategoryName());
        $Data->addParam($this->itemData->getCategoryId());

        return (DB::getQuery($Data) === false || $Data->getQueryNumRows() > 0);
    }

    /**
     * @return CategoryData[]
     */
    public function getAll()
    {
        $query = /** @lang SQL */
            'SELECT category_id, category_name, category_description, category_hash FROM categories ORDER BY category_name';

        $Data = new QueryData();
        $Data->setMapClassName($this->getDataModel());
        $Data->setQuery($query);

        return DB::getResultsArray($Data);
    }
}