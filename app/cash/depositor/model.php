<?php
/**
 * The model file of depositor module of RanZhi.
 *
 * @copyright   Copyright 2009-2018 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @author      Tingting Dai <daitingting@xirangit.com>
 * @package     contact
 * @version     $Id$
 * @link        http://www.ranzhi.org
 */
class depositorModel extends model
{
    /**
     * Construct function.
     * 
     * @param  string $appName 
     * @access public
     * @return void
     */
    public function __construct($appName = '')
    {
        parent::__construct($appName);
        $this->app->loadLang('order', 'crm');
    }
    /**
     * Get depositor by id.
     * 
     * @param  int    $id 
     * @access public
     * @return object
     */
    public function getByID($id)
    {
        return $this->dao->select('*')->from(TABLE_DEPOSITOR)->where('id')->eq($id)->fetch();
    }

    /** 
     * Get depositor list.
     * 
     * @param  string  $tag
     * @param  string  $orderBy 
     * @param  object  $pager 
     * @access public
     * @return array
     */
    public function getList($tag = '', $status = 'all', $orderBy = 'id_desc', $pager = null)
    {
        return $this->dao->select('*')->from(TABLE_DEPOSITOR)
            ->where(1)
            ->beginIF($status != 'all')->andWhere('status')->eq($status)->fi()
            ->beginIF($tag)->andWhere('tags')->like("%{$tag}%")->fi()
            ->orderBy($orderBy)
            ->page($pager)
            ->fetchAll('id');
    }

    /** 
     * Get depositor option menu.
     * 
     * @access public
     * @return array
     */
    public function getPairs($markDisabled = false)
    {
        if(!$markDisabled) return $this->dao->select('id,abbr')->from(TABLE_DEPOSITOR)->fetchPairs('id', 'abbr');
        if($markDisabled)
        {
            $depositorPairs = array();
            $depositorList  = $this->dao->select('id,abbr,status')->from(TABLE_DEPOSITOR)->fetchAll('id');
            foreach($depositorList as $id => $depositor)
            {
                $depositorPairs[$id] = $depositor->abbr;
                if($depositor->status == 'disable') $depositorPairs[$id] .= ' (' . $this->lang->depositor->statusList['disable'] . ')';
            }

            return $depositorPairs;
        }
    }

    /** 
     * Get trade list.
     * 
     * @access public
     * @return array
     */
    public function getTradesAmount()
    {
        return $this->dao->select('depositor, count(*) as amount')->from(TABLE_TRADE)->groupBy('depositor')->fetchPairs();
    }

    /**
     * Get tags of depositors.
     * 
     * @access public
     * @return array
     */
    public function getTags()
    {
        $tags = array();
        $tagList = $this->dao->select('tags')->from(TABLE_DEPOSITOR)->fetchAll();
        foreach($tagList as $tag)
        {
            if(!$tag->tags) continue;
            $depositorTags = explode(',', $tag->tags);
            foreach($depositorTags as $depositorTag) $tags[] = $depositorTag;

        }

        return array_unique($tags);
    }

    /**
     * Create a depositor.
     * 
     * @access public
     * @return int|bool
     */
    public function create()
    {
        $now = helper::now();
        $depositor = fixer::input('post')
            ->add('createdBy', $this->app->user->account)
            ->add('createdDate', $now)
            ->add('editedDate', $now)
            ->removeIF($this->post->type == 'cash', 'public')
            ->get();

        $depositor->tags = trim(str_replace(array('，', ' '), array(',', ''), $depositor->tags), ',');

        $this->dao->insert(TABLE_DEPOSITOR)
            ->data($depositor)
            ->autoCheck()
            ->batchCheck($this->config->depositor->require->create, 'notempty')
            ->exec();

        return $this->dao->lastInsertID();
    }

    /**
     * Update a depositor.
     * 
     * @param  int    $depositorID 
     * @access public
     * @return string|bool
     */
    public function update($depositorID)
    {
        $oldDepositor = $this->getByID($depositorID);

        $depositor = fixer::input('post')
            ->add('editedBy', $this->app->user->account)
            ->add('editedDate', helper::now())
            ->removeIF($this->post->type == 'cash', 'public')
            ->get();

        $depositor->tags = trim(str_replace(array('，', ' '), array(',', ''), $depositor->tags), ',');

        $this->dao->update(TABLE_DEPOSITOR)
            ->data($depositor)
            ->autoCheck()
            ->batchCheck($this->config->depositor->require->edit, 'notempty')
            ->where('id')->eq($depositorID)
            ->exec();

        if(!dao::isError()) return commonModel::createChanges($oldDepositor, $depositor);

        return false;
    }

    /**
     * Forbid a depositor.
     * 
     * @param  int    $depositorID 
     * @access public
     * @return bool
     */
    public function forbid($depositorID)
    {
        $depositor = new stdclass();
        $depositor->status     = 'disable';
        $depositor->editedBy   = $this->app->user->account;
        $depositor->editedDate = helper::now();

        $this->dao->update(TABLE_DEPOSITOR)->data($depositor)->where('id')->eq($depositorID)->exec();

        return dao::isError();
    }

    /**
     * Activate a depositor.
     * 
     * @param  int    $depositorID 
     * @access public
     * @return bool
     */
    public function activate($depositorID)
    {
        $depositor = new stdclass();
        $depositor->status     = 'normal';
        $depositor->editedBy   = $this->app->user->account;
        $depositor->editedDate = helper::now();

        $this->dao->update(TABLE_DEPOSITOR)->data($depositor)->where('id')->eq($depositorID)->exec();

        return dao::isError();
    }

    /**
     * Check depositors. 
     * 
     * @param  array    $depositors 
     * @access public
     * @return void
     */
    public function check($depositors, $start = '', $end = '')
    {
        $balances = $this->dao->select('*')->from(TABLE_BALANCE)
            ->where('date')->in("{$start}, {$end}")
            ->beginif(!empty($depositors))->andWhere('depositor')->in($depositors)->fi()
            ->fetchGroup('depositor', 'date');

        $tradeList = $this->dao->select('*')->from(TABLE_TRADE)
            ->where('parent')->eq(0)
            ->andWhere('`date`')->gt($start)
            ->andWhere('`date`')->le($end)
            ->beginif($depositors)->andWhere('depositor')->in($depositors)->fi()
            ->fetchGroup('depositor', 'id');

        $depositorList = $this->dao->select('*')->from(TABLE_DEPOSITOR)->beginif($depositors)->where('id')->in($depositors)->fi()->fetchAll('id');

        foreach($depositorList as $id => $depositor)
        {
            $depositor->origin    = isset($balances[$id][$start]) ? $balances[$id][$start]->money : 0;
            $depositor->computed  = bcadd($depositor->origin, $this->computeTrades($tradeList, $id), 2);
            $depositor->actual    = isset($balances[$id][$end]) ? $balances[$id][$end]->money : 0;
            $depositor->tradeList = !empty($tradeList[$id]) ? $tradeList[$id] : array();
        }

        return $depositorList;
    }

    /**
     * Compute Trades.
     * 
     * @param  int    $tradeList 
     * @param  int    $depositorID 
     * @access public
     * @return void
     */
    public function computeTrades($tradeList, $depositorID)
    {
        $money = 0;

        if(isset($tradeList[$depositorID]))
        {
            foreach($tradeList[$depositorID] as $item)
            {
                if($item->type == 'in')          $money = bcadd($money, $item->money, 2);
                if($item->type == 'transferin')  $money = bcadd($money, $item->money, 2);
                if($item->type == 'redeem')      $money = bcadd($money, $item->money, 2);
                if($item->type == 'loan')        $money = bcadd($money, $item->money, 2);
                if($item->type == 'out')         $money = bcsub($money, $item->money, 2);
                if($item->type == 'transferout') $money = bcsub($money, $item->money, 2);
                if($item->type == 'invest')      $money = bcsub($money, $item->money, 2);
                if($item->type == 'repay')       $money = bcsub($money, $item->money, 2);
                if($item->type == 'interest')    $money = bcsub($money, $item->money, 2);
            }
        }

        return $money;
    }

    /**
     * Delete a depositor.
     * 
     * @param  int      $depositorID 
     * @access public
     * @return void
     */
    public function delete($depositorID, $null = null)
    {
        $trades = $this->getTradesAmount();
        if(!empty($trades[$depositorID])) return false;

        $this->dao->delete()->from(TABLE_DEPOSITOR)->where('id')->eq($depositorID)->exec();
        return !dao::isError();
    }
}
