<?php

namespace app\index\controller;

use app\index\model\SelectAnswer;
use app\index\model\UserSelect;
use app\index\model\UserSelectItem;
use think\Controller;
use think\Cookie;

class Index extends Controller
{
    const SEQ_START = 0; // 生成随机序列的开始值[0-9]
    const SEQ_END = 9; // 生成随机序列的结束值[0-9]

    /**
     * 用户选择主页面
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        if (!$username = Cookie::get('username')) {
            // 重定向到index模块的index操作
            $this->redirect('index/entry');
        }

        if (!$userSeq = $this->getUserSeq($username)) {
            // cookie不合法
            Cookie::delete('username');
            $this->redirect('index/entry');
        }

        // 获取用户当前提交的轮数
        $curRound = $this->getUserCurRound($username);
        if ($curRound > (self::SEQ_END - self::SEQ_START)) {
            $this->redirect('index/record');
        }

        // 获取用户总选择次数
        $totalTimes = $this->getUserTotalTimes($username);

        // 获取当前轮描述文字
        $curAnswer = $this->getCurAnswer($curRound);

        $initList = range(self::SEQ_START, self::SEQ_END);

        $this->assign([
            'list' => $initList,
            'round' => $curRound,
            'total' => $totalTimes,
            'desc' => $curAnswer ? $curAnswer['num_desc'] : '',
        ]);
        // 模板输出：全路径模板调用
        return $this->fetch(APP_PATH . request()->module() . '/view/index.html');
    }


    /**
     * 用户选择提交某一选项
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function selectSubmit()
    {
        if (!$username = Cookie::get('username')) {
            $this->redirect('index/entry');
        }

        if ($this->request->isPost()) {
            $optVal = $this->request->post('optVal');
            if (!$userSeq = $this->getUserSeq($username)) {
                // cookie不合法
                Cookie::delete('username');
                return [
                    'status' => 0,
                    'msg' => 'cookie illegal',
                ];
            }

            // 获取用户当前提交的轮数
            $curRound = $this->getUserCurRound($username);
            if ($curRound >= 10) {
                $this->redirect('index/record');
            }

            // 获取当前轮答案
            $curAnswer = $this->getCurAnswer($optVal);

            // 新增选择记录
            $selectStatus = $userSeq[$curRound] == $optVal;
            $userItem = new UserSelectItem();
            $userItem->data([
                'username' => $username,
                'ip' => $this->request->ip(),
                'select_value' => $optVal,
                'status' => $selectStatus ? 1 : 0,
                'create_time' => time(),
            ]);
            $res = $userItem->save();

            // 获取用户总选择次数
            $totalTimes = $this->getUserTotalTimes($username);

            return [
                'status' => $res ? ($selectStatus ? 1 : 2) : 0,
                'msg' => $res ? 'ok' : 'db fail',
                'total' => $totalTimes,
                'answer' => $curAnswer ? $curAnswer['num_value'] : '',
            ];
        }

        return [
            'status' => 0,
            'msg' => 'illegal request',
        ];
    }


    /**
     * 参与用户入口
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function entry()
    {
        // post
        if ($this->request->isPost()) {
            $username = $this->request->post('username');

            // 1.写入库
            $user = new UserSelect();
            $curUser = $user->where('username', $username)->find();
            if ($curUser) {
                $res = $curUser->toArray();
            } else {
                $user->data([
                    'username' => $username,
                    'ip' => $this->request->ip(),
                    'create_time' => time(),
                    'update_time' => time(),
                    'random_seq' => json_encode($this->genRandSeq(self::SEQ_START, self::SEQ_END)),
                ]);
                $res = $user->save();
            }

            // 2.设置Cookie 有效期为 3600秒
            if (!Cookie::get('username')) {
                Cookie::set('username', $username, 3600 * 24 * 10);
            }

            return [
                'status' => $res ? 1 : 0,
                'msg' => $res ? 'ok' : 'db fail',
            ];
        }

        // get
        if (Cookie::get('username')) {
            // 重定向到index模块的index操作
            $this->redirect('index/index');
        }

        return $this->fetch(APP_PATH . request()->module() . '/view/entry.html');
    }


    /**
     * 结果页面
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function record()
    {
        if (!$username = Cookie::get('username')) {
            $this->redirect('index/entry');
        }

        // 获取用户当前提交的轮数
        $curRound = $this->getUserCurRound($username);
        if ($curRound < (self::SEQ_END - self::SEQ_START)) {
            $this->redirect('index/index');
        }

        // 获取所有答案列表
        $ansList = $this->getAllAnswer();

        // 获取用户所有选择记录
        $list = $this->getUserRecords($username);
        foreach ($list as $k => $v) {
            $list[$k]['id'] = $k;
            foreach ($ansList as $m => $n) {
                if ($v['select_value'] == $n['num_index']) {
                    $list[$k]['answer'] = $n['num_value'];
                    break;
                }
            }
        }

        $this->assign('list', $list);
        return $this->fetch(APP_PATH . request()->module() . '/view/record.html');
    }


    /**
     * 获取用户当前提交的轮数
     * @param $username
     * @return int
     * @throws \think\Exception
     */
    private function getUserCurRound($username)
    {
        $userItem = new UserSelectItem();
        $rightCount = $userItem->where(['username' => $username, 'status' => 1])->count();
        return $rightCount ?: 0;
    }


    /**
     * 获取用户总选择次数
     * @param $username
     * @return int
     * @throws \think\Exception
     */
    private function getUserTotalTimes($username)
    {
        $userItem = new UserSelectItem();
        $totalCount = $userItem->where(['username' => $username])->count();
        return $totalCount ?: 0;
    }


    /**
     * 获取当前轮描述文字
     * @param $index
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getCurAnswer($index)
    {
        $selectAnswer = new SelectAnswer();
        $answer = $selectAnswer->where(['num_index' => $index])->find();
        if ($answer) {
            return $answer->toArray();
        }
        return array();
    }


    /**
     * 获取所有答案列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getAllAnswer()
    {
        $selectAnswer = new SelectAnswer();
        $answer = $selectAnswer->select();
        if ($answer) {
            return $answer->toArray();
        }
        return array();
    }


    /**
     * 获取用户的随机序列
     * @param $username
     * @return array|mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getUserSeq($username)
    {
        $user = new UserSelect();
        $curUser = $user->where('username', $username)->find();
        if ($curUser) {
            $res = $curUser->toArray();
            return json_decode($res['random_seq'], true);
        }

        return array();
    }


    /**
     * 获取用户所有选择记录
     * @param $username
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getUserRecords($username)
    {
        $userItem = new UserSelectItem();
        $userRec = $userItem->where(['username' => $username, 'status' => 1])->select();
        if ($userRec) {
            return $userRec->toArray();
        }

        return array();
    }


    /**
     * 生成一段随机序列
     * @param $start
     * @param $end
     * @return array
     */
    private function genRandSeq($start, $end)
    {
        $data = range($start, $end);
        shuffle($data);
        return $data;
    }
}
