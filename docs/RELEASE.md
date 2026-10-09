# 公開・環境仕様

## 現在の方針

- Astroの静的ビルドを基本とする
- ビルド成果物だけを公開サーバーへ配置する
- デモと本番の設定は、ビルド時の環境変数で分ける
- ふもと旅館採用サイトと同じ公開方法を基準にする

公開URL、デモURL、FTP配置先は未確定です。確定前に実在するURLを仮置きしません。

## 想定コマンド

```bash
npm run dev
npm run check
npm run build
```

正式なscriptsはAstro初期セットアップ時に`package.json`で確定します。

## 環境

| 環境 | URL | 検索エンジン |
| --- | --- | --- |
| ローカル | Astro開発サーバー | 対象外 |
| デモ | `https://demo-strategic-alliance.tuna-pic.co.jp/` | `noindex, nofollow` |
| 本番 | 未確定 | インデックス対象予定 |

## SEO

- `title`、`description`、canonical、OGPをページごとに設定する
- canonicalとOGP URLは本番URLを基準にする
- デモ環境ではrobotsメタと`robots.txt`の両方でクロールを制限する
- 本番公開前にfavicon、OGP画像、構造化データの要否を確認する
- `robots.txt`とサイトマップを本番公開前に確認する
- 募集要項の仮情報を検索結果へ確定情報として出さない

## フォーム送信

第一段階では、XBiz上のPHP APIから応募内容を担当者へメール通知します。データベースへの保存は、後から同じAPIへ追加します。

- 主送信先（To）：`ken.atnek@gmail.com`
- デバッグ通知先（Bcc）：`debug01@a-fact.co.jp`
- 送信先アドレスとメールサービスの認証情報は環境変数で管理し、フロントエンドへ埋め込まない
- メール送信が成功した場合だけ応募完了画面を表示する
- 職務経歴書はPDF・Word形式、上限5MBとしてメールへ添付し、サーバーへ恒久保存しない（暫定仕様）
- 同一オリジン確認、ハニーポット、サーバー側必須チェックを行う
- デモサイトはBasic認証で保護する
- デモビルドではメール送信APIを含め、`debug01@a-fact.co.jp`だけへ送信する
- 本番ビルドでは`public/api/entry.php`を`dist/api/entry.php`へ含める

### 環境変数

| 変数 | 用途 | 初期値 |
| --- | --- | --- |
| `PUBLIC_ENTRY_API_URL` | フロントエンドから呼び出す送信API | 本番：`/api/entry.php`、デモ：未設定 |
| `ENTRY_MAIL_TO` | 主送信先 | `ken.atnek@gmail.com` |
| `ENTRY_MAIL_BCC` | デバッグ通知先 | `debug01@a-fact.co.jp` |
| `ENTRY_MAIL_FROM` | 送信元 | `recruit@strategic-alliance-inc.com` |
| `ENTRY_TEST_MAIL_TO` | デモURLからのテスト送信先 | `debug01@a-fact.co.jp` |
| `ENTRY_RESUME_MAX_BYTES` | 添付ファイル上限 | `5242880`（5MB） |

PHP側の環境変数が未設定の場合は上記のメールアドレスを使用します。送信にはXBizサーバーのPHPメール機能を使用し、到達性に問題がある場合は認証付きSMTPへ切り替えます。

本番運用前に以下を確定します。

- 応募データの保存先
- 職務経歴書の最終的な許可形式・上限容量
- 担当者通知と自動返信
- スパム対策
- CSRF対策
- 個人情報保護方針と同意
- 送信失敗時の表示と再送方法
- 保存期間と削除運用

ローカル環境にはPHP実行環境がないため、APIの構文確認と実送信テストはXBizサーバーへのテスト配置後に行います。

## 公開前確認

- 本番用URLと環境変数
- 全ページのtitle、description、canonical、OGP
- デモ環境のnoindex
- リンク切れとフォーム遷移
- フォームのバリデーションと二重送信防止
- ファイルアップロードの形式・容量・権限
- 404ページと送信エラー表示
- favicon、OGP画像、ロゴ、人物写真
- Astro checkと本番ビルド
- `dist/`内に開発用ファイルや機密情報がないこと
- 公開サーバーの配置先とバックアップ

## 公開作業

- 静的ビルドの場合は`dist/`の中身だけを公開する
- `src/`、`node_modules/`、設定ファイル、Markdownは公開しない
- 既存公開ファイルを置き換える前に、サーバー側の配置と復旧方法を確認する
