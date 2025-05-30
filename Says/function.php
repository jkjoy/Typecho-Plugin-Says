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

/**
 * 检测用户代理字符串中的操作系统
 * 
 * @param string $userAgent 用户代理字符串，用于识别客户端操作系统和浏览器信息
 * @return array 包含操作系统和浏览器信息的数组
 */
function getOS($userAgent = '') {
    // 返回值
    $result = ['os' => 'Unknown', 'browser' => 'Unknown'];
    // 操作系统检测 - 用一个正则匹配所有可能的操作系统
    if (preg_match('/(?:Windows NT (\d+\.\d+)|iPhone|iPad|Macintosh|Mac OS X|Android|Linux)/i', $userAgent, $osMatches)) {
        switch (true) {
            case isset($osMatches[1]):
                // Windows 版本映射
                $winVersions = ['10.0' => 'Windows 10/11', '6.3' => 'Windows 8.1', '6.2' => 'Windows 8', '6.1' => 'Windows 7', '6.0' => 'Windows Vista', '5.1' => 'Windows XP'];
                $result['os'] = $winVersions[$osMatches[1]] ?? 'Windows';
                break;
            case stripos($osMatches[0], 'iPhone') !== false:
                $result['os'] = 'iOS';
                break;
            case stripos($osMatches[0], 'iPad') !== false:
                $result['os'] = 'iPadOS';
                break;
            case stripos($osMatches[0], 'Mac') !== false:
                $result['os'] = 'macOS';
                break;
            case stripos($osMatches[0], 'Android') !== false:
                $result['os'] = 'Android';
                break;
            case stripos($osMatches[0], 'Linux') !== false:
                $result['os'] = 'Linux';
                break;
         }
    }
    return $result['os'];
}

/**
 * 获取用户浏览器信息
 * 通过解析用户代理字符串（User Agent）来判断用户正在使用的浏览器类型及版本
 * 
 * @param string $userAgent 用户代理字符串，默认为空，如果为空将使用当前请求的用户代理
 * @return string 返回浏览器名称及版本，如果没有匹配到已知的浏览器，则返回空字符串
 */
function getBrowser($userAgent = '') {
    // 浏览器检测 - 用一个正则匹配所有主流浏览器及版本
    if (preg_match('/(Edg|Edge)\/(\d+)\.|Chrome\/(\d+)\.|Firefox\/(\d+)\.|Version\/(\d+)\..*Safari|MSIE (\d+)\.|rv:(\d+)\..*Trident/i', $userAgent, $browserMatches)) {
        if (!empty($browserMatches[1]) && !empty($browserMatches[2])) {
            // Edge
            $result['browser'] = 'Microsoft Edge ' . $browserMatches[2];
        } elseif (!empty($browserMatches[3])) {
            // Chrome
            $result['browser'] = 'Google Chrome ' . $browserMatches[3];
        } elseif (!empty($browserMatches[4])) {
            // Firefox
            $result['browser'] = 'Mozilla Firefox ' . $browserMatches[4];
        } elseif (!empty($browserMatches[5])) {
            // Safari
            $result['browser'] = 'Apple Safari ' . $browserMatches[5];
        } elseif (!empty($browserMatches[6])) {
            // IE (MSIE)
            $result['browser'] = 'IE ' . $browserMatches[6];
        } elseif (!empty($browserMatches[7])) {
            // IE (Trident)
            $result['browser'] = 'IE ' . $browserMatches[7];
        }
    }
    return $result['browser'];
}


/**
 * 根据用户代理获取平台信息
 * 
 * 此函数首先尝试解析用户代理以获取来源信息如果解析失败或结果为空，
 * 则根据配置返回默认的操作系统信息、浏览器信息或自定义内容
 * 
 * @param string $agent 用户代理字符串，默认为空
 * @return string 平台信息或自定义内容，如果没有配置则返回空字符串
 */
function getPlatform($agent = '') {
    // 直接解析用户代理获取来源信息
    $result = getSource($agent);
    if (!empty($result)) return $result;
    
    // 获取默认来源配置
    $defaultSource = \Utils\Helper::options()->plugin('Says')->defaultSource;
    
    // 根据配置处理来源信息
    if (empty($defaultSource)) return '';
    if ($defaultSource == '1') return getOS($agent);
    if ($defaultSource == '2') return getBrowser($agent);
    if ($defaultSource == '3') return getOS($agent) . ' ' . getBrowser($agent);
    
    // 返回自定义内容
    return $defaultSource;
}