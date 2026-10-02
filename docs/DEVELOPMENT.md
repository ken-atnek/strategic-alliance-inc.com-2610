# 開発ルール

## 技術方針

- Astro、TypeScript、SCSS、Vanilla JavaScriptを使用する
- ふもと旅館採用サイトのAstro構成を基準にする
- React、Vue、SvelteなどのUIフレームワークは追加しない
- Tailwind CSSは使用しない
- HTML構造は`.astro`ファイルへ手書きする
- JavaScriptが無効でも本文と主要導線を確認できる構造にする
- 静的ビルドを基本とし、サーバー機能は要件確定後に判断する

## 想定ディレクトリ

Astro初期セットアップ時は、以下を基準に最小構成から作成します。

```text
src/
├── assets/
│   └── images/
├── components/
│   ├── common/
│   ├── forms/
│   └── sections/
├── layouts/
│   └── BaseLayout.astro
├── pages/
├── scripts/
└── styles/
    ├── foundation/
    ├── global/
    ├── utilities/
    └── globals.scss
public/
docs/
```

ページファイル名とURLは、URL設計の確定後に追加します。

## コンポーネント

- ページファイルはレイアウトとセクションを表示順に並べる役割に留める
- Header、Footerなどは`src/components/common/`に置く
- LPの各セクションは`src/components/sections/`に置く
- フォーム共通部品は`src/components/forms/`に置く
- コンポーネント名はPascalCaseにする
- 対応するSCSSは同じ階層へ`ComponentName.module.scss`として置く
- 見出しや画像1つだけで細分化しない
- 同じUIが複数回登場する場合だけ子コンポーネント化を検討する

```astro
---
import styles from './Header.module.scss';
---

<header class={styles.header}></header>
```

## 実装の進め方

1. 対象Figmaフレームの構造とスクリーンショットを確認する
2. 使用する画像とSVGを特定する
3. `.astro`で意味のあるHTML構造を作る
4. 対応するSCSSでレイアウトを作る
5. ブラウザ表示とFigmaを比較する
6. ビルドまたはチェックを通してから次の範囲へ進む

複数セクションのSCSSを一括で大幅に書き換えません。

## SCSS

- リセット、変数、mixin、タイポグラフィは`src/styles/`へ集約する
- コンポーネント固有のスタイルは対応する`module.scss`へ記載する
- デザイントークンは`docs/DESIGN_TOKENS.md`を基準に変数化する
- 共通化は、同じ意図の指定が複数箇所で必要になってから行う
- foundationはCSSを直接出力せず、変数・関数・mixinを提供する
- クラス名はキャメルケースを基本にし、Astroから`styles.className`で参照する
- 状態クラスは`isOpen`、`isActive`、`hasError`のように役割を明確にする
- ネストを深くしすぎず、親セレクタへの依存を増やさない
- Figma取得コードのTailwindクラスや絶対配置をそのまま移植しない

## レイアウト

- 現在のデザイン基準はPC 1440px
- コンテンツ幅、余白、画像位置はFigmaを確認して決める
- 装飾や重なり以外は、Flexbox、Grid、通常フローを優先する
- `position: absolute`は装飾や意図的な重なりへ限定する
- 1440px以外で致命的に崩れない構造にはするが、スマホデザインを独自に確定しない
- スマホ対応の指示が入った時点で、ブレイクポイントと再配置を設計する

## フォント

- 基本フォントは`Noto Sans JP`
- ウェイトはRegular、Medium、Boldを基準にする
- フォント定義は`src/styles/foundation/_typography.scss`へ集約する
- `font-family`を各コンポーネントへ分散させない
- 読み込み方法とライセンスは実装時に確認する

## フォーム

- `form`、`label`、`input`、`select`、`textarea`、`button`を用途に合わせて使用する
- 入力欄とラベルを関連付ける
- 必須・任意を視覚だけで伝えない
- エラーは対象入力とプログラム上も関連付ける
- キーボードで全項目を操作できるようにする
- 送信処理や入力値保持の方式は、未確定のまま仮実装で固定しない
- 職務経歴書アップロードは、許可形式・容量・保存先の確定後に実装する

## 画像

- 最適化する写真は`src/assets/images/`へ置き、Astroの画像機能を使用する
- 変換不要のSVG、favicon、OGP画像は`public/`へ置く
- 内容を伝える画像には具体的な`alt`を付ける
- 純粋な装飾画像は空の`alt`またはCSS背景として扱う
- 幅と高さを明示し、レイアウトシフトを避ける
- Figmaの一時アセットURLをソースへ残さない
- 画像を独自にトリミング・描き直し・代替しない

## JavaScript

- UIフレームワークを導入せず、必要な箇所だけVanilla JavaScriptで実装する
- DOM取得時は操作対象の存在を確認する
- 操作対象ごとに初期化処理を分ける
- SEO上必要な本文をJavaScriptで後から生成しない
- 動きを追加する場合は`prefers-reduced-motion`を考慮する

## コメントヘッダー

新規作成する主要ソースには、用途・参照元・作成日・更新日が分かるコメントヘッダーを記載します。

対象：

- `src/**/*.astro`
- `src/**/*.scss`
- 手動作成する`src/**/*.ts`

```astro
---
/* =======================================
 * ストラテジックアライアンス採用 Header
 * URL: /src/components/common/Header.astro
 * Referenced in: /src/pages/index.astro
 * Created: YYYY-MM-DD
 * Last updated: YYYY-MM-DD
 * ======================================= */
import styles from './Header.module.scss';
---
```

日付は`YYYY-MM-DD`で統一し、修正時は`Last updated`だけを更新します。

## 検証

- HTMLの見出し階層とランドマーク
- 横スクロール、文字切れ、画像比率
- ヘッダー追従時の重なり
- リンクとフォームのキーボード操作
- フォームラベル、必須表示、エラーの関連付け
- Figmaとブラウザの主要余白・文字・画像配置
- 変更範囲に応じたAstro checkとビルド
