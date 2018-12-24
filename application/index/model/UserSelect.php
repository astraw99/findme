<?php
/**
 * User: astraw99
 * Mail: wangchengiscool@gmail.com
 * Date: 2018/12/22 18:10
 * Desc: ...
 */

namespace app\index\model;

use think\Model;

class UserSelect extends Model
{
    protected $pk = 'id';

    // 设置当前模型对应的完整数据表名称
    protected $table = 'user_select';

    // 设置模型的数据集返回类型，可直接使用toArray/toJson()
    protected $resultSetType = 'collection';
}