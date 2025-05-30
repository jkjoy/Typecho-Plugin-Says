<?php
namespace TypechoPlugin\Says;

use Typecho\Widget;
use Typecho\Db;
use Typecho\Plugin\Exception as PluginException;

// 防止直接运行
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 引入公共函数
require_once 'function.php';

class Action extends Widget {
    
    private $db;
    private $options;
    private $tablePrefix;

    /**
     * Action 构造函数
     *
     * @param Request $request Typecho 请求对象
     * @param Response $response Typecho 响应对象
     */
    public function __construct($request, $response) {
        // 必须调用父类构造函数
        parent::__construct($request, $response); 
        // 数据库实例
        $this->db = Db::get();
        // 插件配置
        $this->options = Widget::widget('Widget_Options')->plugin('Says');
        // 表名
        $this->saysTable = $this->db->getPrefix() . 'says';
    }
    
    /**
     * 获取所有 Memo (GET /api/v1/memo)
     * 参数类型 limit=8&page=1
     */
    public function getMemos() {
        // 每页条数
        $limit = $this->request->filter('int')->get('limit', 8);
        // 默认第 1 页
        $page = $this->request->filter('int')->get('page', 1);   
        // 偏移量
        $offset = ($page - 1) * $limit;
        try {
            // 查询
            $memos = (array)$this->db->fetchAll($this->db->select()->from($this->saysTable)->where('status = ?', 1)->order('created_at', Db::SORT_DESC)->offset($offset)->limit($limit));
            // 获取总数
            $total = (int)$this->db->fetchObject($this->db->select('COUNT(*) as count')->from($this->saysTable)->where('status = ?', 1))->count;
            // 格式化数据
            $data = [];
            foreach ($memos as $memo) {
                $data[] = [
                    'uid' => $memo['uuid'],
                    'content' => $memo['content'],
                    'created_at' => intval($memo['created_at']),
                    'updated_at' => intval($memo['updated_at']),
                    'up' => intval($memo['up']),
                    'down' => intval($memo['down']),
                    'from' => getPlatform($memo['agent'])
                ];
            }
            // 返回数据
            $this->response->throwJson(['status' => 1, 'message' => 'ok', 'data' => $data]);
        } catch (PluginException $e) {
            // 抛出错误
            $this->response->throwJson(['status' => 0, 'message' => 'Internal server error: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 检查认证状态 (POST /memos/api/v1/auth/status)
     * 参数类型 authorization: Bearer + token
     */
    public function authStatus() {
        // 如果插件停用，直接返回404
        if (!$this->options->isOpen) throw new Exception(_t('接口不存在'), 404);
        try {
            // 获取 token
            $token = $this->getAuthToken();
            // 登陆失败
            if (!$token) $this->response->throwJson(['status' => 0, 'message' => 'Unauthorized']);
            // 验证 token
            if ($token !== $this->options->token) $this->response->throwJson(['status' => 0, 'message' => 'Unauthorized']);
            // 返回用户信息
            $user = $this->db->fetchRow($this->db->select('screenName', 'mail')->from($this->db->getPrefix() . 'users')->where('uid = ?', $this->options->userId));
            // 用户不存在则抛出错误
            if (!$user) $this->response->throwJson(['status' => 0, 'message' => _t('指定的说说用户不存在.')]);
            // 生成头像URL (使用Gravatar)
            $avatarUrl = __TYPECHO_GRAVATAR_PREFIX__ . md5(strtolower(trim($user['mail']))) . '?s=64&d=identicon';
            // 返回数据
            $this->response->throwJson(['id' => $this->options->userId, 'nickname' => $user['screenName'], 'avatar' => $avatarUrl]);
        } catch (PluginException $e) {
            // 抛出错误
            $this->response->throwJson(['status' => 0, 'message' => 'Internal server error: ' . $e->getMessage()]);
        }
    }
    
    /**
     * 创建 Memo (POST /memos/api/v1/memo)
     * 创建一个 Memo
     * 参数类型 content: 创建内容
     */
    public function createMemo() {
        // 如果插件停用，直接返回404
        if (!$this->options->isOpen) throw new Exception(_t('接口不存在'), 404);
        try {
            // 获取 token
            $token = $this->getAuthToken();
            // 登陆失败
            if (!$token) $this->response->throwJson(['status' => 0, 'message' => 'Unauthorized']);
            // 验证 token
            if ($token !== $this->options->token) $this->response->throwJson(['status' => 0, 'message' => 'Unauthorized']);
            // 获取 JSON 请求数据
            $data = json_decode(file_get_contents('php://input'), true);
            // 验证 JSON 解析是否成功
            if (json_last_error() !== JSON_ERROR_NONE) return $this->response->throwJson(['status' => 0, 'message' => 'Invalid JSON data']);
            // 获取请求数据
            $content = isset($data['content']) ? trim($data['content']) : '';
            // 提取UA
            $agent = $this->request->getAgent();
            // 验证内容
            list($valid, $message) = validateSayContent($content);
            // 验证失败直接抛出错误
            if (!$valid) return $this->response->throwJson(['status' => 0, 'message' => $message]);
            // 当前时间
            $currentTime = time();
            // 插入数据库
            $insertId = $this->db->query($this->db->insert($this->saysTable)->rows([
                'uuid' => generateUUID(),
                'user_id' => $this->options->userId,
                'content' => $content,
                'agent' => $agent, // 直接加在ua里，把来源
                'ip' => $this->request->getIp() ?? '0.0.0.0',
                'status' => (strtoupper($data['visibility'] ?? 'public') === 'PUBLIC') ? 1 : 0,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ]));
            // 直接返回数据
            $this->response->throwJson(['status' => 1, 'message' => 'ok']);
        } catch (PluginException $e) {
            // 抛出错误
            $this->response->throwJson(['status' => 0, 'message' => 'Internal server error: ' . $e->getMessage()]);
        }
    }

    /**
     * 获取认证令牌
     */
    private function getAuthToken():string {
        // 从 Header 中获取 Bearer Token
        $authorization = $this->request->getHeader('Authorization');
        // 返回 Token
        return $authorization ? str_replace('Bearer ', '', $authorization) : '';
    }
}