<?php

#参考资料 https://github.com/wangshub/wechat_jump_game


/**
 * 按压力度参数，根据实际表现进行调节
 */
define('PRESS_COEFFICIENT', 1.393);

/**
 * 截图
 * @author madaima
 */
function screenshot()
{
//    system('adb shell screencap -p /sdcard/screen.png');
//    system('adb pull /screenshot/screen.png .');
    //部分手机 使用上面命令没有权限 可以使用下面的命令 但是需要 mingw 环境支持 ，windows 下可以使用git 自带的 git bash 使用。
    system('adb shell screencap -p | sed \'s/\r$//\' > screenshot/screen.png');
}


/**
 * @author luduanfeng@gmail.com
 */
function init()
{
    for ($id = 0; ; $id++) {
        echo sprintf("%04d: ", $id);
        screenshot();
        $image = imagecreatefrompng("screenshot/screen.png");
        //取棋子坐标
        list($px, $py) = find_piece($image);
        //取棋盘坐标
        list($bx, $by) = find_board($image, $px, $py);

        echo sprintf("piece:[%d, %d] => board:[%d, %d] ", $px, $py, $bx, $by);

        // 记录棋子和棋盘中心点
        imagefilledellipse($image, $px, $py, 10, 10, 0xFF0000);
        imagefilledellipse($image, $bx, $by, 10, 10, 0xFF0000);
        imagepng($image, "screenshot/{$id}.png");

        // 计算按压距离、时间
        $dist = sqrt(pow($bx - $px, 2) + pow($by - $py, 2));
        $time = $dist * PRESS_COEFFICIENT;
        $time = round($time, 0);
        echo sprintf("distance: %f, time: %f\n", $dist, $time);

        jump($time);

        //随机延迟 尝试绕过检测
        sleep(mathRandom());
    }
}

/**
 * 使用adb 模拟按压
 * @param $time
 * @author madaima
 */
function jump($time)
{
    system('adb shell input swipe 320 410 320 410 ' . $time);
}


/**
 * 查找棋子中心点坐标
 * @param $image
 * @return array
 * @author madaima
 */
function find_piece($image)
{
    $width = imagesx($image);
    $height = imagesy($image);

    $piece_x_sum = 0;
    $piece_x_c = 0;
    $piece_y_max = 0;

    for ($y = $height / 3 * 2; $y > $height / 3; $y--) {
        for ($x = 0; $x < $width; $x++) {
            $pixel = imagecolorat($image, $x, $y);

            //当前像素点 rgb
            list($r, $g, $b) = get_rgb($pixel);
            //50 ~ 60、53 ~ 63、95 ~ 110 为棋子 RGB区间
            if (($r > 50 && $r < 60) && ($g > 53 && $g < 63) && ($b > 95 && $b < 110)) {
                $piece_x_sum += $x;
                $piece_x_c += 1;
                $piece_y_max = max($y, $piece_y_max);
            }

        }
    }

    $piece_x = (int)($piece_x_sum / $piece_x_c);
    // 找到的是棋子最底部位置 故上移棋子底盘高度的一半
    $piece_y = (int)($piece_y_max - 20);

    return [$piece_x, $piece_y];
}

/**
 * 查找下一块棋盘
 * @param $image
 * @param $piece_x
 * @param $piece_y
 * @return array
 * @author madaima
 */
function find_board($image, $piece_x, $piece_y)
{
    $width = imagesx($image);
    $height = imagesy($image);

    $board_x_sum = 0;
    $board_x_count = 0;
    $board_start_y = 0;

    if ($piece_x < ($width / 2)) {
        $board_x_start = $piece_x;
        $board_x_end = $width;
    } else {
        $board_x_start = 0;
        $board_x_end = $piece_x;
    }

    //找上顶点，从y轴到 x轴 逐个像素扫描
    for ($y = $height / 3; $y < $height * 2 / 3; $y++) {
        $bg_pixel = get_rgb(imagecolorat($image, 0, $y));//背景色RGB 背景为渐变色 取对应y轴
        for ($x = $board_x_start; $x < $board_x_end; $x++) {

            //解决小人头 比下一块高的情况
            if (abs($x - $piece_x) < 70) {
                continue;
            }

            $pixel_rgb = get_rgb(imagecolorat($image, $x, $y));//当前像素RGB
            if ((abs($pixel_rgb[0] - $bg_pixel[0]) + abs($pixel_rgb[1] - $bg_pixel[1]) + abs($pixel_rgb[2] - $bg_pixel[2])) > 10) {
                $board_x_sum += $x;
                $board_x_count += 1;
                $board_start_y = $y;
                //分析图片 可得下一个区域 顶部为一个或多个像素点组成的一条直线
                //累计求中点即可 避免数据异常 只记一个y轴。
                break 2;
            }
        }
    }
    //通过上顶点坐标和 得出X轴坐标
    if ($board_x_sum) {
        $board_x = (int)($board_x_sum / $board_x_count);
    }

    //从上顶点往下 + 274 的位置开始向上找颜色与上顶点一样的点，为下顶点.
    // 274 为方块最大值
    $last_pixel = get_rgb(imagecolorat($image, $board_x, $board_start_y));
    $board_y_last = 0;
    for ($i = $board_start_y + 274; $i > $board_start_y; $i--) {
        $pixel = get_rgb(imagecolorat($image, $board_x, $i));
        if (abs($pixel[0] - $last_pixel[0]) + abs($pixel[1] - $last_pixel[1]) + abs($pixel[2] - $last_pixel[2]) < 10) {
            $board_y_last = $i;
            break;
        }
    }

    $board_y = intval(($board_start_y + $board_y_last) / 2);

    //如果上一跳命中中间，则下个目标中心会出现 r245 g245 b245 的点，利用这个属性弥补上一段代码可能存在的判断错误
    for ($y = $board_start_y; $y < $board_start_y + 200; $y++) {
        $pixel = get_rgb(imagecolorat($image, $board_x, $y));
        if (abs($pixel[0] - 245) + abs($pixel[1] - 245) + abs($pixel[2] - 245) == 0) {
            $board_y = $y + 10;
            break;
        }
    }

    return [$board_x, $board_y];

}

/**
 * 颜色索引值 转 RGB
 * @param $pixel
 * @return array
 * @author madaima
 */
function get_rgb($pixel)
{
    return $rgb = [
        ($pixel >> 16) & 0xFF,
        ($pixel >> 8) & 0xFF,
        $pixel & 0xFF
    ];
}

/**
 * 随机数
 * @param float $min
 * @param float $max
 * @return float
 * @author madaima
 */
function mathRandom($min = 1, $max = 1.5)
{
    return $min + mt_rand() / mt_getrandmax() * ($max - $min);
}

init();