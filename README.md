# KeyNest / Market

KeyNest 是一个类似闲鱼的虚拟商品交易平台，支持用户发布和交易各类虚拟商品 / 虚拟资源，包含前台市场、用户控制台、商品发布、自动发货、余额充值提现、会员等级、支付接口、系统设置、后台日志和自动更新等功能。

## 项目目录

```text
admin/       管理后台页面
api/         后端 API 接口
assets/      前台静态资源
config/      安装和数据库配置
core/        核心类库，例如数据库、日志
public/      公开访问入口和资源副本
templates/   前台页面模板
data/        运行数据、安装锁等
logs/        系统日志目录
```

## 部署说明

1. 上传项目文件到网站目录。
2. 访问：

```text
/install.php
```

3. 按页面提示配置数据库。
4. 安装完成后访问后台：

```text
/admin/
```

默认后台账号以安装流程或数据库初始化为准。

## 后台功能

后台包含：

- 总览
- 用户管理
- 商品管理
- 订单记录
- 充值提现
- 卡密管理
- 会员等级配置
- 系统设置
- 系统更新
- 系统日志

## 会员等级

会员等级已改为后台动态配置，不再由前台写死。

后台可以设置：

- 等级名称
- 等级描述
- 排序权重
- 开通价格
- 单商品最大账号数
- 最多商品数
- 交易手续费
- 发布费 / 账号
- 是否启用
- 是否允许前台升级
- 图标和卡片样式

数据库中会员等级使用独立表：

```text
kn_membership_levels
```

## 自动上传到 GitHub

本项目的本地网站目录是：

```text
C:\Users\Administrator\Desktop\00
```

GitHub 上传工具目录是：

```text
C:\Users\Administrator\Desktop\github
```

双击下面文件即可自动上传到 GitHub：

```text
C:\Users\Administrator\Desktop\github\一键上传Market.bat
```

上传脚本会：

1. 复制 `C:\Users\Administrator\Desktop\00` 的网站文件。
2. 放到 `C:\Users\Administrator\Desktop\github\Market`。
3. 自动提交并推送到 GitHub 仓库。

GitHub 仓库地址：

```text
https://github.com/Aze0920/Market
```

## 后台自动更新

后台新增了“系统更新”。

更新源：

```text
https://github.com/Aze0920/Market
```

自动更新时会拉取到：

```text
C:\Users\Administrator\Desktop\github\Market
```

再同步到网站目录。

更新时会保留运行数据：

```text
config/database.php
data/
logs/
```

避免覆盖数据库配置、安装锁和日志。

## 安全提醒

不要上传以下敏感内容：

```text
.github-token
logs/
数据库密码
私钥
真实支付密钥
```

当前上传脚本默认不会上传 `logs/`。
