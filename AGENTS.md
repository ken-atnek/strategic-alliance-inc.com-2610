# AGENTS.md

## 基本方針

株式会社ストラテジックアライアンスの採用サイトを、支給Figmaと確定原稿に基づいてAstroで制作します。

- 依頼範囲外の大幅な変更をしない
- 変更は小さく分け、画面単位・コンポーネント単位で確認する
- 不明な仕様や事実情報を推測で確定しない
- 実装難易度を理由にデザインを簡略化しない
- Figma上の合成画像を、そのままWeb表示用素材として使用しない

## 作業開始時の確認順

1. `AGENTS.md`
2. `docs/PROJECT_STATUS.md`
3. `docs/PROJECT_SPEC.md`
4. `docs/PROJECT_SPEC.md`に記載したFigma
5. 実装時は`docs/DEVELOPMENT.md`
6. UIを触る時は`docs/UI_SPEC.md`
7. 色・余白・文字を触る時は`docs/DESIGN_TOKENS.md`
8. ビルド・SEO・公開時は`docs/RELEASE.md`

## 仕様の優先順位

1. ユーザーからの最新指示
2. Figmaの最新デザイン
3. `docs/PROJECT_SPEC.md`
4. その他のMarkdown
5. 既存実装

FigmaとMarkdownに差がある場合は、事実情報・原稿はMarkdown、見た目はFigmaを優先します。判断できない場合は実装前に確認します。

## 実装方針

- 技術・コーディング方針は`docs/DEVELOPMENT.md`を正とする
- UI動作は`docs/UI_SPEC.md`を正とする
- 公開環境・フォーム送信・SEO方針は`docs/RELEASE.md`を正とする
- デザイン値は`docs/DESIGN_TOKENS.md`とFigmaを参照する
- 既存ファイルの構成と命名を確認してから追加する
- HTMLは`.astro`へ手書きし、自動生成したコードをそのまま採用しない
- React、Vue、Svelte、Tailwind CSSは、明示的な指示がない限り追加しない

## Figma確認

- 実装前に対象フレームの構造とスクリーンショットを確認する
- Figma取得コードは参考情報として扱い、Astro・SCSSの既存方針へ変換する
- 画像、SVG、ロゴはFigma内の正しいレイヤーと配置を確認する
- セクションを1つ実装するごとにブラウザ表示とFigmaを比較する
- Figmaへの書き込みは、ユーザーから明示的に依頼された場合だけ行う

## 対象範囲

- 採用LP
- エントリーフォーム Step 1〜4
- 入力内容確認画面
- 応募完了画面

応募者管理画面は、現時点ではこの実装プロジェクトの対象外です。

## 注意点

- 現在のFigmaはPC版が基準。スマホ版はPCデザインを基準にレスポンシブ対応する
- 募集要項には仮情報が含まれるため、確定原稿と混同しない
- フォームの送信先、再読み込み時の入力保持、ファイルアップロード、個人情報管理は未確定
- 公開URL、デモURL、応募データの保存方法は未確定
- 未確定事項は`docs/PROJECT_STATUS.md`へ集約し、複数ファイルへ重複記載しない
