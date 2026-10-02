# 株式会社ストラテジックアライアンス 採用サイト

株式会社ストラテジックアライアンスの採用LP、募集要項、エントリーフォームをAstroで制作するプロジェクトです。

## 現在の状態

- 実装前のドキュメント整理まで完了
- Figmaの対象画面と主要ノードを確認済み
- Astroの最小構成、共通レイアウト、foundation SCSSを作成済み
- トップページはビルド確認用の空構成
- 素材の書き出し・配置は未着手

進捗と次の作業は`docs/PROJECT_STATUS.md`を確認してください。

## ドキュメント

- `AGENTS.md`：作業ルールと参照順
- `CLAUDE.md`：Claude向けの入口
- `docs/PROJECT_STATUS.md`：現在地、未確定事項、次の作業
- `docs/PROJECT_SPEC.md`：対象画面、原稿、Figma参照先
- `docs/DEVELOPMENT.md`：Astro、SCSS、コンポーネント規約
- `docs/UI_SPEC.md`：ヘッダー、遷移、フォームの動作仕様
- `docs/DESIGN_TOKENS.md`：カラー、文字、余白、UIルール
- `docs/RELEASE.md`：環境、SEO、フォーム送信、公開前確認

各分野の正本は上記の対応ファイルです。仕様を別ファイルへ重複して記載しません。

## 技術方針

ふもと旅館採用サイトと同じ方針を基準にします。

- Astro
- TypeScript
- SCSS Modules
- Vanilla JavaScript
- 静的ビルドを基本とする

バージョンとnpm scriptsは`package.json`を正とします。

## 開発コマンド

```bash
npm run dev
npm run check
npm run build
npm run build:production
```

## デザイン

- Figma：<https://www.figma.com/file/Q4lQervx90Xl2FFdFRj312?node-id=0-1&p=f&m=dev&type=design&fuid=1485198985601365801>
- 基準幅：PC 1440px
- スマホ版：現時点では対象外

実装対象ノードは`docs/PROJECT_SPEC.md`に記載しています。
