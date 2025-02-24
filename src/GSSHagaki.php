<?php

namespace GSSHagaki;

require(__DIR__ . '/../vendor/autoload.php');

use \Exception;
use GuzzleHttp\Client;
use setasign\Fpdi\TcpdfFpdi;
use TCPDF_FONTS;

class GSSHagaki
{

    /**
     * 読み込まれるCSVファイル。
     *
     * @var \SplFileObject $file
     */
    private $file = null;

    /**
     * はがきインスタンス
     *
     * @var Hagaki
     */
    private $hagaki;

    /**
     * オプション配列。
     *
     * @var array
     */
    private $options = [];

    /**
     * GSSHagaki constructor.
     *
     * @param $url
     * @param array $options
     */
    public function __construct($url, $options = [])
    {
        try {
            $url = $this->fixURL($url);
        } catch (Exception $e) {
            var_dump($e->getMessage());
            exit;
        }

        $this->hagaki = new Hagaki();
        $this->options($options);
        $datas = $this->readData($url);
        $this->writeData($datas);
        $this->output();
    }

    /**
     * オプションを設定する。
     *
     * @param $options
     */
    private function options($options) {
        $this->options = $options;
	    if ( isset($options['template']) && (boolean)$options['template']) { // はがきテンプレートを表示する
            $this->hagaki->use_template = true;
        }
        if ( isset($options['to_zenkaku']) && (boolean)$options['to_zenkaku'] ) { // 半角数字を全角にする
            $this->options['to_zenkaku'] = true;
        } else {
            $this->options['to_zenkaku'] = false;
        }
	    if ( isset($options['credit']) && (boolean)$options['credit']) { // クレジット表記
		    $this->options['credit'] = true;
	    } else {
		    $this->options['credit'] = false;
	    }
        if ( isset($options['debug']) && (boolean)$options['debug']) { // デバッグ
            $this->options['debug'] = true;
        } else {
            $this->options['debug'] = false;
        }
	    if ( isset ( $options['zipcode_x'] ) ) { // 差出人 下限高さ
		    $this->hagaki->zipcode_x = (float) $options['zipcode_x'];
	    }
	    if ( isset ( $options['zipcode_gap'] ) ) { // 差出人 下限高さ
		    $this->hagaki->zipcode_gap = (float) $options['zipcode_gap'];
	    }
	    if ( isset ( $options['owner_shita_takasa'] ) ) { // 差出人 下限高さ
		    $this->hagaki->owner_shita_takasa = (float) $options['owner_shita_takasa'];
	    }
    }

    /**
     * GoogleSpreadSheetのURLが正しいかチェックし、CSVダウンロード用に修正する。
     *
     * インプット例 https://docs.google.com/spreadsheets/d/1yfMIdt8wgBPrMY3UwiCTsX3EN_2gcLCmPAEy8dfYeLY/edit?usp=sharing
     * @param $url
     *
     * @return string
     * @throws Exception
     */
    private function fixURL($url) {
        // 1. docs.google.comで始まっている
        // 1. URLパスの最後がexportになっている

        // またフォーマットについては正しくない場合に修正を行う。
        // 1. format=csvになっている

        // 先頭が https://docs.google.com/ で始まっているか確認する ココ重要！
        if ( 0 !== strpos($url, 'https://docs.google.com/spreadsheets')) {
            throw new Exception('GoogleスプレッドシートのURLではないようです。');
        }

        // 末尾の/editを/exportに変える（厳密にはURL中の…、だけれど、ハッシュで/editが出る可能性は低いと見ている
        // e.g. https://docs.google.com/spreadsheets/d/1yfMIdt8wgBPrMY3UwiCTsX3EN_2gcLCmPAEy8dfYeLY/edit#gid=0
        $url = str_replace('/edit', '/export', $url);

        // #gid=0があれば取り除く
        // e.g. https://docs.google.com/spreadsheets/d/1yfMIdt8wgBPrMY3UwiCTsX3EN_2gcLCmPAEy8dfYeLY/export#gid=0
        $url = str_replace('#gid=0', '', $url);
        // $url = str_replace('#gid=0', '', $url);

        // 末尾に?format=csvを足す
        // e.g. https://docs.google.com/spreadsheets/d/1yfMIdt8wgBPrMY3UwiCTsX3EN_2gcLCmPAEy8dfYeLY/export
        if ( FALSE === strpos($url, 'format=csv') ) {
            if (FALSE !== strpos($url, '#')) { // #があった場合にはうまくいかない
                $url = str_replace('#', '?format=csv&', $url);
            } else {
                // @link https://stackoverflow.com/questions/5809774/manipulate-a-url-string-by-adding-get-parameters
                $query = parse_url($url, PHP_URL_QUERY); // クエリ文字列だけを抜きだす
                $url   .= ! empty($query) // 既にクエリ文字列が設定されているかどうか
                    ? '&format=csv' // 設定されていれば&で連結し
                    : '?format=csv'; // そうでなければ?で連結する
            }
        }
        // 完成したURLの例
        // e.g. https://docs.google.com/spreadsheets/d/1yfMIdt8wgBPrMY3UwiCTsX3EN_2gcLCmPAEy8dfYeLY/export?usp=sharing&format=csv
        return $url;
    }

    /**
     * @param $url
     *
     * @return array
     */
    private function readData($url)
    {

        $this->file = new \SplFileObject($url);
        $this->file->setFlags(\SplFileObject::READ_CSV);
        $header = []; // カラム名が記載された見出し行
        $datas  = [];

        // CSVデータを読み出す
        while ( ! $this->file->eof() && $row = $this->file->fgetcsv()) {
            if ( ! count($header)) { // 見出し行が無かった場合
                $header = $row;
                continue;
            }
            if (count($row) === 1 ||  // 空行だったりした場合
                empty($row[0]) // 最初の列が空の行は省く
            ) {
                continue;
            }
            $data    = array_combine($header, $row); // 見出し行を連想配列のキーに設定する
            $datas[] = $data;
        }

        return $datas;
    }

    /**
     * データを書き込む。
     *
     * @param $datas
     *
     * @return mixed
     */
    private function writeData($datas) {
        $this->hagaki->defineHagaki();
        foreach ($datas as $data) {
            if ( empty($data['zipcode']) && $data['address_1'] ) {
                // 郵便番号と住所1がない場合にはスキップする
                continue;
            }
            $this->hagaki->addPage();
            $this->hagaki->zipcode($data['zipcode']);
            $this->hagaki->address(
                $this->to_zenkaku($data['address_1']),
                $this->to_zenkaku($data['address_2']).
                $this->to_zenkaku($data['address_3'])
            );


            $names = [];
            for($i=0; $i<4; $i++) {
                $names[$i]['first_name'] = $data['first_name_'. ($i+1)];
                $names[$i]['suffix'] = $data['suffix_'. ($i+1)];
            }
            $this->hagaki->names($data['family_name'], $names);

            $this->hagaki->owner_zipcode($data['owner_zipcode']);
            $this->hagaki->owner_address(
                $this->to_zenkaku($data['owner_address_1']),
                $this->to_zenkaku($data['owner_address_2'])
            );
            $this->hagaki->owner_name($data['owner_name']);

            if ( isset($this->options['debug']) && $this->options['debug'] ) {
                $this->hagaki->addVersion();
            }
	        if ( isset($this->options['credit']) && $this->options['credit'] ) {
		        $this->hagaki->credit();
	        }
        }
        return $datas;
    }

    /**
     * 半角数字を全角にする
     */
    private function to_zenkaku($str) {
        return str_replace([0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
            ['〇', '一', '二', '三', '四', '五', '六', '七', '八', '九'],
            $str
    );
    }

    /**
     * ブラウザに出力する。
     *
     * @link  https://qiita.com/horimislime/items/325848fcf1e3dc6bd53a
     * 下記のリンクは恐らく古い情報で、'O'は未サポート。 @see method of tcpdf
     * @link https://stackoverflow.com/questions/31198949/how-to-send-the-file-inline-to-the-browser-using-php-with-tcpdf
     */
    private function output()
    {
        // header("Content-type: application/pdf");
        header("Content-type: force-download");
        // $this->hagaki->Output('name.pdf', 'O');
        // If the above does not work, it is just better to use the php header() function.
        $this->hagaki->output('hagaki.pdf', 'D');
    }
}
