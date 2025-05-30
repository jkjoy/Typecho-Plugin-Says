<?php
namespace TypechoPlugin\Says;
/**
 * 公共函数库
 * 
 * @package Says
 */
// 防止直接访问
if(!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 生成UUID
 * 
 * @return string 生成的UUID
 */
function generateUUID() {
    return sprintf(
        '%04x%04x%04x%04x%04x%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
/**
 * 格式化说说数据
 * 
 * @param array $say 原始说说数据
 * @return array 格式化后的数据
 */
function formatSayData($say) {
    return [
        'uid' => $say['uuid'],
        'content' => $say['content'],
        'created_at' => intval($say['created_at']),
        'updated_at' => intval($say['updated_at']),
        'up' => intval($say['up']),
        'down' => intval($say['down']),
        'from' => $say['source'] ?: 'web'
    ];
}
/**
 * 验证说说内容
 * 
 * @param string $content 说说内容
 * @return array [是否有效, 错误消息]
 */
function validateSayContent($content) {
    // 验证内容
    if (empty($content))  return [false, '说说内容不能为空'];
    // 说说内容不能太长
    if (mb_strlen($content, 'UTF-8') > 1000) return [false, '内容太长（最多1000字符）'];
    // 返回判定结果
    return [true, ''];
}

/**
 * 返回JSON
 * 
 */
function outputJson($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
}

/**
 * 根据高级规则匹配源字符串，支持正则捕获组引用
 * 
 * @param string $source 源字符串
 * @param string $rule 规则字符串，格式为多行的"名称 \\index|/正则表达式/"
 * @return string 匹配的名称或正则捕获组的值，如果没有匹配则返回\"未知\"
 */
function getSource($source, $rule = '') {
    // 如果没有，直接返回未知
    if (empty($source))  return '';
    // 获取规则字符串，取消转义
    if (empty($rule)) $rule = html_entity_decode(\Utils\Helper::options()->plugin('Says')->platform);
    // 将规则字符串按行分割
    $ruleLines = explode("\n", $rule);
    // 按行拆分
    foreach ($ruleLines as $line) {
        // 去除行首尾空白
        $line = trim($line);
        // 跳过空行
        if (empty($line)) continue;
        // 提取名称和规则部分
        if (preg_match('/^(.*?)\|\|(.*?)/U', $line, $ruleMatches)) {
            // 默认名称
            $defaultName = $ruleMatches[1]; 
            // 正则表达式内容
            $pattern = $ruleMatches[2]; 
            // 修复正则表达式中的\\d等转义字符（假设d+实际上应该是\\d+）
            // $pattern = str_replace('d+', '\\d+', $pattern);
            //$pattern = str_replace('s', '\\s', $pattern);
            // 使用正则表达式匹配源字符串
            if (preg_match('/' . $pattern . '/i', $source, $matches)) {
                // 核心替换逻辑：动态解析 \\数字 并映射到 $matches
                $defaultName = preg_replace_callback(
                    '/\\\\\\\\\d+/',  // 匹配 \\数字（如 \\1, \\2）
                    function($m) use ($matches) {
                        $index = (int)substr($m[0], 2);  // 提取数字部分
                        return $matches[$index] ?? $m[0]; // 存在则替换，否则保留原文本
                    },
                    $defaultName
                );
                return $defaultName; // 返回处理后的值
            }
        }
    }
    // 如果没有匹配到任何规则，返回未知
    return '';
}