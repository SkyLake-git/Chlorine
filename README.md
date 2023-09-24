# Chlorine

Block suspicious network activity / 怪しいネットワークアクティビティを検知し、ブロックします  
For PocketMine-MP / PocketMine-MP 用プラグイン  
Supported languages: Japanese / 日本語のみサポート

## 詳しく教えて
プレイヤーからの不正なパケット送信などによるサービス拒否攻撃(Dos)をある程度防ぎます。  
パケットのデコードにかかった時間、パケットのサイズを監視し、  
異常な値が複数検出されたプレイヤーを処罰します。

## コマンド
コマンドを使用するには `chlorine.commands` またはオペレーター権限が必要です

- `/chlorine [i|inspect] <player>`

対象プレイヤーのネットワークアクティビティを確認します

- `/chlorine [most_suspicious|most|most_sus]`

サーバー内で最も怪しいプレイヤーのインスペクション結果を表示します

## コンフィグ
`plugin_data/Chlorine/config.yml` から編集できます  

補足: 
`decoding load` はサーバーの1tick (50ms)のうちデコード時間が占める割合

| キー | 説明 | デフォルト値 |
| ---- | ---- | ---- |
| `disconnect_message` | コンソールに表示される切断時のメッセージ | Suspicious activity detected |
| `disconnect_screen_message` | プレイヤーに表示される切断時のメッセージ | Disconnected from server |
| `decoding_load_threshold` | 一発処罰の閾値 (デコード時間割合) | 10.0 |
| `packet_length_threshold` | 一発処罰の閾値 (パケットサイズ) | 2097152 |
| `max_violation` | 最大警告レベル (超えると処罰) | 350 |
| `history_length` | パケット履歴の最大サイズ (大きくするとメモリ使用量が増加します) | 200 |
| `block_address_timeout` | 処罰したプレイヤーのアドレスをブロックする時間 (0以下で無効、無効にするとキックのみ) | 350 |
| `decoding_load_violation_thresholds` | 以下レベルの警告を実行する閾値 (デコード時間割合) | [詳細](#decoding_load_violation_thresholds) |

### `decoding_load_violation_thresholds`
| レベル | 警告数 | デフォルト値 |
| ---- | ---- | ---- |
| `level_alert` | 40 | 0.25 |
| `level_warn` | 80 | 0.4 |
| `level_alert` | 135 | 0.75 |
| `level_alert` | 165 | 1.2 |
