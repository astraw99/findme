<?php
/**
 * User: astraw99
 * Mail: wangchengiscool@gmail.com
 * Date: 2018/12/23 13:52
 * Desc: ...
 */

namespace app\index\model;

use think\Model;

class SelectAnswer extends Model
{
    protected $pk = 'id';

    // 设置当前模型对应的完整数据表名称
    protected $table = 'select_answer';

    // 设置模型的数据集返回类型，可直接使用toArray/toJson()
    protected $resultSetType = 'collection';
}