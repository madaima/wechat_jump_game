# 使用世界上最好的语言 来玩微信跳一跳

参考 https://github.com/wangshub/wechat_jump_game 实现的 php 版本.



## 环境

- PHP-CLI 
- GIT BASH 或同等 mingw 环境
- ADB 可以到[这里](https://adb.clockworkmod.com/)下载

## 原理说明

1. 将手机点击到《跳一跳》小程序界面；
2. 用 ADB 工具获取当前手机截图，并用 ADB 将截图保存至电脑，

* 方案一（需在 mingw 环境中执行 ，可使用 git 自带的 git bash 中执行）
```shell
   adb shell screencap -p | sed 's/\r$//' > screenshot/screen.png
```
* 方案二 （命令行中执行即可 ， 部分手机提示无权限）
```shell
    adb shell screencap -p /sdcard/screen.png
    adb pull /screenshot/screen.png .
```

3. 逐列扫描像素点，匹配棋子位置、棋盘位置；
4. 计算棋子与棋盘中心点位置，乘以一定的系数，得到时间；
5. 通过 ADB ，触发操作；

```shell
    adb shell input swipe x y x y time(ms)
```

## 操作步骤(android)

- 打开USB调试，使用 USB 线连接手机；
- 确保执行 `adb devices` 可以看到设备列表；
- 打开微信跳一跳游戏，点击开始游戏；
- 运行`php run.php`；

## 效果

- 描点
![描点](http://ww1.sinaimg.cn/large/0060lm7Tly1fn5iqksyjij30u01hcgml.jpg)

- 分数 
![描点](http://ww3.sinaimg.cn/large/0060lm7Tly1fn5iqkse18j30u01hcgmf.jpg)
