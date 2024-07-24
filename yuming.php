<?php
/**==============================================================================================================
 * 1.分析域名是否 数字、 字母 、拼音（几拼）  、杂米 ,如果不懂域名品相，可以到http://www.wanmi.cc/wm/ 玩米网上查看。
 * 2.域名品相分析以下例子必须要支持：
 *    111.com 三位数字
 *    jmkj.com 四位纯声母
 *    jmkjabc.com 7位字母
 *    xian.com 单拼
 *    xianguang.com 两拼
 *    a1a2.com 杂米
 *    以上仅为举例，非以上域名也必须可以分析出具体品相。
 * 3.域名的输入格式：abc.com、abc.com.cn、abc.cn等
 *
 * 思路拆解:
 * 1.首先要明确域名的组成部分，域名由两部分组成，主域名和顶级域名。
 * 2.实现域名分析的调度函数，根据域名的组成部分，调用相应的函数进行分析。
 * 3.实现域名分析的函数，根据域名的组成部分，进行相应的分析。
 *
 * ==============================================================================================================
 * @author Roog Guxinpei@qq.com
 * ==============================================================================================================*/


if (PHP_VERSION_ID < 80000) {
    throw new Exception('PHP版本必须大于8.0');
}

/**
 * 域名品相类
 */
interface DomainStyle
{

    /**
     * @param int $nums 位数，例如两拼，三纯数字
     */
    public function __construct(int $nums);

    /**
     * 获取品相名称
     * @return string
     */
    public function styleName(): string;

    /**
     * 获取品相数量
     * @return int
     */
    public function nums(): int;

    /**
     * 获取品相检查器
     * @return StyleChecker
     */
    public static function role(): StyleChecker;


}

/**
 * 域名品相检测接口
 * Interface DomainStyleChecker
 * @package JumingTec\Domain
 * @author Roog guxinpei@qq.com
 */
interface DomainStyleChecker
{
    public function check(): DomainStyle;

}

/**
 * 品相检查器
 */
interface StyleChecker
{
    public function check(Domain $domain): DomainStyle|false;
}


/**
 * 域名分析类
 * Class DomainParser
 * @package JumingTec\Domain
 * @version 1.0
 * @date 2024-07-23
 * @author Roog guxinpei@qq.com
 * @example $domain = new DomainParser('baidu.com');
 *          $domain->parser();
 */
class DomainParser
{

    private $style = [

    ];

    public function __construct(
        private Domain $domain
    )
    {

    }

    /**
     * 注册域名品相
     * @return DomainStyle::class[]
     */
    public function styleRegister(): array
    {
        //todo::先以纯优先级进行检测，如果后期过多，可以考虑使用策略模式替代此处
        return $this->style;
    }

    public function insertStyle(string $domainStyle): void
    {
        $this->style[] = $domainStyle;
    }


    /**
     * @throws DomainParserException
     */
    public function parser(): DomainStyle
    {
        //注册域名品相
        $styles = $this->styleRegister();

        //依照优先级检查域名品相
        foreach ($styles as $style) {
            /** @var DomainStyle $style */
            $styleResponse = $style::role()->check($this->domain);
            if ($styleResponse) {
                return $styleResponse;
            }
        }

        //如果并没有该域名品相，抛出异常
        throw new DomainParserException('未知品相');
    }

}


class DomainParserException extends \Exception
{

}


/**
 * 纯声母品相
 */
class FullPinyinStyle implements DomainStyle
{


    /**
     * NumberStyle constructor.
     * @param int $nums
     */
    public function __construct(
        private int $nums //纯声母位数
    )
    {

    }


    /**
     * 获取品相名称
     * @return string
     */
    public function styleName(): string
    {
        return '纯拼音品相';
    }

    /**
     * 获取品相数量
     * @return int
     */
    public function nums(): int
    {
        return $this->nums;
    }

    /**
     * 获取品相检查器
     * @return StyleChecker
     */
    public static function role(): StyleChecker
    {
        return new FullPinyinStyleChecker();
    }

}

/**
 * 全拼品相检查器
 */
class FullPinyinStyleChecker implements StyleChecker
{
    /**
     * 全拼哈希桶缓存
     * @var string[]
     */
    private array $FullPinyinHashCache;


    /**
     * 检查全拼品相
     * 如果是全拼，那么将字符串拆开来看，如果每个字母都在全拼字典中，则是全拼，否则不是全拼
     * 我们使用贪婪的计数策略，确保取最小的全拼字数
     * @param Domain $domain
     * @return DomainStyle|false
     */
    public function check(Domain $domain): DomainStyle|false
    {

        //获取域名字符串
        $domainFullString = $domain->getMainDomain();
        //规划出所有可能性
        //todo::也许彻底改写成节点类的形式可读性会好一点？
        $allPossiblesArray[] = $this->splitPinyin($domainFullString);
        //遍历将树形结构改为一维数组
        $list = $this->treeToList($allPossiblesArray);
        if (empty($list)) {
            return false;
        }
        //返回最小值
        $nums = min(array_column($list, 'final_word_count'));
        return new FullPinyinStyle($nums);

    }

    /**
     * 分割字符串
     * @param string $domainFullString
     * @param int $wordsCount
     * @param array $finalSplit
     * @return mixed
     */
    private function splitPinyin(string $domainFullString, int $wordsCount = 0, array $finalSplit = []): mixed
    {
        //将字符串转换为数组
        $domainLetterArray = str_split($domainFullString);
        //翻转该数组逆向取词
        $domainLetterArray = array_reverse($domainLetterArray);

        //循环该数组，链接当前字符,如果在全拼字典中找不到，则说明不是全拼
        //使用贪婪算法，总是试图取得更多字符的可能性
        $currentStr = ''; //当前字符串
        $possiblesNodes = []; //所有的可能性节点
        foreach ($domainLetterArray as $letter) {
            $currentStr = $letter . $currentStr;
            if ($this->inFullPinyin($currentStr)) {
                //如果在全拼字典中，则继续贪婪获取下一位
                $leastWords = substr($domainFullString, 0, -strlen($currentStr));
                //当已经没有剩余的字符串，则返回结果
                if (strlen($leastWords) === 0) {
                    $finalSplit[] = $currentStr;
                    $possiblesNodes['final_split'] = $finalSplit;
                    $possiblesNodes['final_word_count'] = count($finalSplit);
                    return $possiblesNodes;
                } else {
                    $insert = $finalSplit;
                    $insert[] = $currentStr;
                    $possiblesNodes['kids'][] = $this->splitPinyin($leastWords, $wordsCount + 1, $insert);
                }
            }
            //继续获取下一个字母
        }
        return $possiblesNodes;
    }

    /**
     * 检查是否在全拼字典中
     * @param $words
     * @return bool
     */
    public function inFullPinyin($words): bool
    {
        //如果缓存为空，则初始化
        $this->FullPinyinHashCache = empty($this->FullPinyinHashCache)
            ? array_flip($this->getFullPinyinDict())
            : $this->FullPinyinHashCache;

        return $this->inHashDict($this->FullPinyinHashCache, $words);
    }


    public function inHashDict(array $dict, string $words): bool
    {
        return isset($dict[$words]);
    }


    /**
     * 获取全拼字典
     * @return array
     */
    public function getFullPinyinDict(): array
    {
        return [
            'a',
            'ai',
            'an',
            'ang',
            'ao',
            'ba',
            'bai',
            'ban',
            'bang',
            'bao',
            'bei',
            'ben',
            'beng',
            'bi',
            'bian',
            'biao',
            'bie',
            'bin',
            'bing',
            'bo',
            'bu',
            'ca',
            'cai',
            'can',
            'cang',
            'cao',
            'ce',
            'cen',
            'ceng',
            'cha',
            'chai',
            'chan',
            'chang',
            'chao',
            'che',
            'chen',
            'cheng',
            'chi',
            'chong',
            'chou',
            'chu',
            'chua',
            'chuai',
            'chuan',
            'chuang',
            'chui',
            'chun',
            'chuo',
            'ci',
            'cong',
            'cou',
            'cu',
            'cuan',
            'cui',
            'cun',
            'cuo',
            'da',
            'dai',
            'dan',
            'dang',
            'dao',
            'de',
            'dei',
            'den',
            'deng',
            'di',
            'dia',
            'dian',
            'diao',
            'die',
            'ding',
            'diu',
            'dong',
            'dou',
            'du',
            'duan',
            'dui',
            'dun',
            'duo',
            'e',
            'ei',
            'en',
            'eng',
            'er',
            'fa',
            'fan',
            'fang',
            'fei',
            'fen',
            'feng',
            'fiao',
            'fo',
            'fou',
            'fu',
            'ga',
            'gai',
            'gan',
            'gang',
            'gao',
            'ge',
            'gei',
            'gen',
            'geng',
            'gong',
            'gou',
            'gu',
            'gua',
            'guai',
            'guan',
            'guang',
            'gui',
            'gun',
            'guo',
            'ha',
            'hai',
            'han',
            'hang',
            'hao',
            'he',
            'hei',
            'hen',
            'heng',
            'hong',
            'hou',
            'hu',
            'hua',
            'huai',
            'huan',
            'huang',
            'hui',
            'hun',
            'huo',
            'ji',
            'jia',
            'jian',
            'jiang',
            'jiao',
            'jie',
            'jin',
            'jing',
            'jiong',
            'jiu',
            'ju',
            'juan',
            'jue',
            'jun',
            'ka',
            'kai',
            'kan',
            'kang',
            'kao',
            'ke',
            'kei',
            'ken',
            'keng',
            'kong',
            'kou',
            'ku',
            'kua',
            'kuai',
            'kuan',
            'kuang',
            'kui',
            'kun',
            'kuo',
            'la',
            'lai',
            'lan',
            'lang',
            'lao',
            'le',
            'lei',
            'leng',
            'li',
            'lia',
            'lian',
            'liang',
            'liao',
            'lie',
            'lin',
            'ling',
            'liu',
            'lo',
            'long',
            'lou',
            'lu',
            'luan',
            'lue',
            'lun',
            'luo',
            'lv',
            'ma',
            'mai',
            'man',
            'mang',
            'mao',
            'me',
            'mei',
            'men',
            'meng',
            'mi',
            'mian',
            'miao',
            'mie',
            'min',
            'ming',
            'miu',
            'mo',
            'mou',
            'mu',
            'na',
            'nai',
            'nan',
            'nang',
            'nao',
            'ne',
            'nei',
            'nen',
            'neng',
            'ni',
            'nian',
            'niang',
            'niao',
            'nie',
            'nin',
            'ning',
            'niu',
            'nong',
            'nou',
            'nu',
            'nuan',
            'nue',
            'nun',
            'nuo',
            'nü',
            'o',
            'ou',
            'pa',
            'pai',
            'pan',
            'pang',
            'pao',
            'pei',
            'pen',
            'peng',
            'pi',
            'pian',
            'piao',
            'pie',
            'pin',
            'ping',
            'po',
            'pou',
            'pu',
            'qi',
            'qia',
            'qian',
            'qiang',
            'qiao',
            'qie',
            'qin',
            'qing',
            'qiong',
            'qiu',
            'qu',
            'quan',
            'que',
            'qun',
            'ran',
            'rang',
            'rao',
            're',
            'ren',
            'reng',
            'ri',
            'rong',
            'rou',
            'ru',
            'rua',
            'ruan',
            'rui',
            'run',
            'ruo',
            'sa',
            'sai',
            'san',
            'sang',
            'sao',
            'se',
            'sen',
            'seng',
            'sha',
            'shai',
            'shan',
            'shang',
            'shao',
            'she',
            'shei',
            'shen',
            'sheng',
            'shi',
            'shou',
            'shu',
            'shua',
            'shuai',
            'shuan',
            'shuang',
            'shui',
            'shun',
            'shuo',
            'si',
            'song',
            'sou',
            'su',
            'suan',
            'sui',
            'sun',
            'suo',
            'ta',
            'tai',
            'tan',
            'tang',
            'tao',
            'te',
            'tei',
            'teng',
            'ti',
            'tian',
            'tiao',
            'tie',
            'ting',
            'tong',
            'tou',
            'tu',
            'tuan',
            'tui',
            'tun',
            'tuo',
            'wa',
            'wai',
            'wan',
            'wang',
            'wei',
            'wen',
            'weng',
            'wo',
            'wu',
            'xi',
            'xia',
            'xian',
            'xiang',
            'xiao',
            'xie',
            'xin',
            'xing',
            'xiong',
            'xiu',
            'xu',
            'xuan',
            'xue',
            'xun',
            'ya',
            'yan',
            'yang',
            'yao',
            'ye',
            'yi',
            'yin',
            'ying',
            'yo',
            'yong',
            'you',
            'yu',
            'yuan',
            'yue',
            'yun',
            'za',
            'zai',
            'zan',
            'zang',
            'zao',
            'ze',
            'zei',
            'zen',
            'zeng',
            'zha',
            'zhai',
            'zhan',
            'zhang',
            'zhao',
            'zhe',
            'zhei',
            'zhen',
            'zheng',
            'zhi',
            'zhong',
            'zhou',
            'zhu',
            'zhua',
            'zhuai',
            'zhuan',
            'zhuang',
            'zhui',
            'zhun',
            'zhuo',
            'zi',
            'zong',
            'zou',
            'zu',
            'zuan',
            'zui',
            'zun',
            'zuo',
        ];
    }

    private function treeToList(array $allPossiblesArray, array $resultList = []): array
    {
        foreach ($allPossiblesArray as $item) {
            if (isset($item['kids'])) {
                $resultList = $this->treeToList($item['kids'], $resultList);
            }
            if (isset($item['final_split']) && isset($item['final_word_count'])) {
                $resultList[] = [
                    'final_split' => $item['final_split'],
                    'final_word_count' => $item['final_word_count']
                ];
            }
        }

        return $resultList;

    }


}


/**
 * 纯字母品相
 */
class LetterAndNumberStyle implements DomainStyle
{


    /**
     * NumberStyle constructor.
     * @param int $nums
     */
    public function __construct(
        private int $nums //纯声母位数
    )
    {

    }


    /**
     * 获取品相名称
     * @return string
     */
    public function styleName(): string
    {
        return '数字字母风格(杂)';
    }

    /**
     * 获取品相数量
     * @return int
     */
    public function nums(): int
    {
        return $this->nums;
    }

    /**
     * 获取品相检查器
     * @return StyleChecker
     */
    public static function role(): StyleChecker
    {
        return new LetterAndNumberStyleChecker();
    }

}

/**
 * 纯字母品相
 */
class LetterAndNumberStyleChecker implements StyleChecker
{
    public function check(Domain $domain): DomainStyle|false
    {
        //判断是否权威A~Z, a~z 1-9
        if (preg_match('/^[a-zA-Z0-9\-\x{4e00}-\x{9fa5}]+$/u', $domain->getMainDomain())) {
            $count = mb_strlen($domain->getMainDomain());
            return new LetterAndNumberStyle($count);
        }

        return false;
    }
}

/**
 * 纯声母品相
 */
class PureInitialConsonantStyle implements DomainStyle
{


    /**
     * NumberStyle constructor.
     * @param int $nums
     */
    public function __construct(
        private int $nums //纯声母位数
    )
    {

    }


    /**
     * 获取品相名称
     * @return string
     */
    public function styleName(): string
    {
        return '纯声母品相';
    }

    /**
     * 获取品相数量
     * @return int
     */
    public function nums(): int
    {
        return $this->nums;
    }

    /**
     * 获取品相检查器
     * @return StyleChecker
     */
    public static function role(): StyleChecker
    {
        return new PureInitialConsonantStyleChecker();
    }

}

/**
 * 纯数字品相检查器
 */
class PureInitialConsonantStyleChecker implements StyleChecker
{
    public function check(Domain $domain): DomainStyle|false
    {
        $mainDomain = $domain->getMainDomain();

        //循环去除所有声母
        foreach ($this->chineseInitialConsonantDict() as $initialConsonant) {
            $mainDomain = str_replace($initialConsonant, '', $mainDomain);
            //如果去除声母后为空，则说明是纯声母品相
            if (empty($mainDomain)) {
                return new PureInitialConsonantStyle(strlen($domain->getMainDomain()));
            }
        }

        return false;
    }

    /**
     * 获取声母字典
     * @return array
     */
    public function chineseInitialConsonantDict(): array
    {
        return [
            'b', 'p', 'm', 'f', 'd', 't', 'n', 'l', 'g', 'k', 'h', 'j', 'q', 'x', 'r', 'z', 'c', 's', 'w', 'y',
            'zh', 'ch', 'sh', //todo::不确定是否有声母，先实现
        ];
    }
}


/**
 * 纯字母品相
 */
class PureLetterStyle implements DomainStyle
{


    /**
     * NumberStyle constructor.
     * @param int $nums
     */
    public function __construct(
        private int $nums //纯声母位数
    )
    {

    }


    /**
     * 获取品相名称
     * @return string
     */
    public function styleName(): string
    {
        return '纯字母品相';
    }

    /**
     * 获取品相数量
     * @return int
     */
    public function nums(): int
    {
        return $this->nums;
    }

    /**
     * 获取品相检查器
     * @return StyleChecker
     */
    public static function role(): StyleChecker
    {
        return new PureLetterStyleChecker();
    }

}

class PureLetterStyleChecker implements StyleChecker
{
    public function check(Domain $domain): DomainStyle|false
    {
        //判断是否权威A~Z, a~z
        if (preg_match('/^[a-zA-Z]+$/', $domain->getMainDomain())) {
            $count = strlen($domain->getMainDomain());
            return new PureLetterStyle($count);
        }

        return false;
    }
}

class PureNumberStyle implements DomainStyle
{


    /**
     * NumberStyle constructor.
     * @param int $nums
     */
    public function __construct(
        private int $nums //数字位数
    )
    {

    }


    /**
     * 获取品相名称
     * @return string
     */
    public function styleName(): string
    {
        return '纯数字品相';
    }

    /**
     * 获取品相数量
     * @return int
     */
    public function nums(): int
    {
        return $this->nums;
    }

    /**
     * 获取品相检查器
     * @return StyleChecker
     */
    public static function role(): StyleChecker
    {
        return new PureNumberStyleChecker();
    }

}

/**
 * 纯数字品相检查器
 */
class PureNumberStyleChecker implements StyleChecker
{
    public function check(Domain $domain): DomainStyle|false
    {
        if (is_numeric($domain->getMainDomain())) {
            $count = strlen($domain->getMainDomain());
            return new PureNumberStyle($count);
        }
        return false;
    }
}

/**
 * 域名分析类
 * Class DomainAnalysis
 * @package JumingTec\Domain
 * @version 1.0
 * @date 2024-07-23
 * @php 8.1
 * @example $domain = new DomainAnalysis('baidu.com');
 *          $domain->getMainDomain(); // baidu
 *          $domain->getTopDomain(); // .com
 *          $domain->getDomain(); // baidu.com
 */
class Domain
{


    private string $mainDomain;

    private string $topDomain;

    /**
     * @param string $domain
     * @throws InvalidArgumentException 参数错误
     */
    public function __construct(
        private string $domain
    )
    {
        if (empty($this->domain)) {
            throw new InvalidArgumentException('域名不能为空');
        }
        $domain = explode('.', $this->domain);
        if (count($domain) < 2) {
            throw new InvalidArgumentException('域名格式错误');
        }
        // 主域名
        $this->mainDomain = $domain[0];
        unset($domain[0]);
        // 顶级域名
        $this->topDomain = '.' . implode('.', $domain);
    }

    /**
     * 获取主域名
     * @return string
     * @example baidu
     */
    public function getMainDomain(): string
    {
        return $this->mainDomain;
    }

    /**
     * 获取顶级域名
     * @return string
     * @example .com|.com.cn|.cn
     */
    public function getTopDomain(): string
    {
        return $this->topDomain;
    }

    /**
     * 获取完整域名
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

}


//如果是GET请求,输出HTML
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    require 'index.html';
} else {
//    如果是POST请求，输出运算结果
//    获取域名
    try {
        $domainString = $_POST['domain'] ?? '';
        //实例化域名对象
        $domain = new Domain($domainString);
        //实例化域名分析类
        $parser = new DomainParser($domain);

        array_map(fn($style) => $parser->insertStyle($style), [
            //数字类
            PureNumberStyle::class,          //纯数字品相

            //字母类
            PureInitialConsonantStyle::class,//纯声母品相
            FullPinyinStyle::class,          //纯拼音品相
            PureLetterStyle::class,          //纯字母品相

            //杂类
            LetterAndNumberStyle::class,     //字母数字组合品相(杂)

        ]);
        //获取域名品相
        $style = $parser->parser();
        echo "域名品相分析结果：" . json_encode([
                '品相' => $style->styleName(),
                '字数' => $style->nums(),
                '域名' => $domainString,
            ], JSON_UNESCAPED_UNICODE);
    } catch (DomainParserException|InvalidArgumentException $e) {
        echo $e->getMessage();
    }
}

