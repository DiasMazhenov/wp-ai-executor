# WP AI Executor — актуальный handoff report

Дата фиксации: **2026-09-22 01:33:54 +05:00 (Asia/Almaty)**
Репозиторий: `/Users/diasmazhenov/vibecode/wp-ai-executor`
Ветка: `main`
Целевая страница: **WordPress post=5214, Pricing Contract Live v123**
Новые pages, posts, drafts и новые Elementor roots в этом этапе не создавались.

## Срез исходников и live

- Baseline перед этапом: HEAD `438e5d9`; runtime commit `d34e401`; source/live `v02.11.141`.
- Итоговый source HEAD: `4ae6f71cbdc0692efc1136f5766b7e0b6e2b1083` (`Guard stale operation Vision captures`).
- Runtime/source version: **v02.11.142**.
- WP Pusher update активного плагина: **PASS**; Plugins page показывает активный `WP AI Executor v02.11.142`.
- Первая ZIP-загрузка создала одну неактивную копию `v02.11.142`; она не активна и ожидает удаления из списка плагинов. Это не страница и не post.
- `git push origin main`: **PASS**, `438e5d9..4ae6f71`.
- Исходные и итоговые flags: `Design Decision Engine=active`, `Deterministic Design Pipeline=active`.
- Рабочий tree содержит только ранее существовавшие untracked audit/history файлы; они не включались в commit.

## Фактическая схема и routing

```text
prompt
  -> BriefIR v1
  -> typed DesignPlan v1
  -> WidgetCapabilityRegistry / LayoutReport
  -> ElementorIR v2
  -> native Elementor compiler
  -> structural/semantic/layout validation
  -> existing transaction/update/read-back boundary
  -> editor/public render
  -> scoped Vision/reconcile
```

При `pipeline=active` и `EDDE=active` первым выбирается local deterministic pipeline. EDDE не запускается вторым конкурирующим compiler-ом и не создаёт второй write path. Legacy provider JSON, EDDE hero slice, transaction/rollback, page-update, retry/undo и browser sync сохранены.

| Pipeline | EDDE | Фактический action path | Provider calls | Page writes | Статус |
|---|---|---|---:|---:|---|
| off | off | legacy provider | 1 | 1 | PASS, contract harness |
| off | active | EDDE | 0 | 1 | PASS, contract harness |
| shadow | active | EDDE + shadow comparison | 0 | 1 | PASS; shadow не пишет |
| active | active | local deterministic pipeline | 0 | 1 | PASS, v140 live trace |

Для установленного v142 на уже сохранённой странице новый write не запускался; live install проверялся read-only. В active/active trace нет двойной записи.

## Изменения v142

### Единый target guard для stale pending

`includes/elementor/operation-ledger.php` получил общий read-only `wpae_design_operation_target_status()`. Он проверяет post scope, root IDs, наличие корней в актуальном `_elementor_data`, `saved_hash` и `target_fingerprint`. Для отсутствующего root возвращается `stale_target/root_missing/class=unknown_target_change`; rollback классифицируется отдельно как `confirmed_rollback`, если snapshot действительно совпадает.

`includes/elementor/editor-chat.php` локализует `target_status` и `reviewable`.
`includes/llm/llm.php` не переводит stale operation и mismatched target в reconcile/review state и возвращает scoped conflict codes.
`assets/js/elementor-llm-chat.js` скрывает кнопку проверки stale pending, не снимает screenshot с отсутствующего target и не отправляет такой target в общий Vision fallback.

### Контракт и package

- Добавлены checks для current target, missing root и saved-hash mismatch.
- Добавлены JS contract checks для stale review guard.
- `wp-ai-executor.php` поднят до v02.11.142.
- `wpae-package.json` пересобран: 90 tracked runtime files, hash mismatches `0`.

## Историческое удаление hero

Историческая причина удаления не установлена. Подтверждены только факты WordPress revisions:

- revision `5303` удаляет из diff `Тихая форма`, `АРХИТЕКТУРА`, `Пространство для идей`, body и обе CTA;
- revision `5300` содержит hero и сохраняет pricing/FAQ.

На том же post=5214 штатно восстановлена revision `5300`, затем восстановленная Elementor model сохранена штатной кнопкой Elementor. После save/reload public `_elementor_data` и DOM снова содержат hero `a9282de`. Pricing `b898e72` и FAQ `13568dc` не пересобирались и не удалялись.

## Текущая операция и reconcile

После reload до v142 localized pending metadata содержала:

```json
{
  "operation_id": "wpae-89007d964d7436ab",
  "operation_identity": "e12c2e08-2952-4906-8dbb-f66d3ed42eb1",
  "revision": 5,
  "root_ids": ["1fa90e6"],
  "current_state": "written",
  "saved_hash": "present",
  "target_fingerprint": "present"
}
```

Актуальные roots post=5214: `a9282de`, `13568dc`, `b898e72`; root `1fa90e6` отсутствует. До v142 попытка штатного reconcile доходила до `wpae_vision_capture_failed` и оставляла `written/render_review_pending`. Это не было доказательством read-back или Vision.

После установки v142 и reload editor:

- кнопка `Проверить сохранённый результат` отсутствует (`pendingCount=0`);
- missing target не отправляется в screenshot capture и provider Vision fallback;
- операция не переводится в `reviewed`/`completed` и не деградирует старым acknowledgement;
- текущие page roots и saved content не изменены.

Successful selected-scope patch остаётся отдельным evidence layer: operation `wpae-20260921192047-21f06cd2`, element `bc09c44`, Vision score `100`, confidence `100%`. Он не является доказательством whole-operation reconcile.

## Ledger/idempotency checks

Production helper и contract harness проверяют:

- повтор delivery того же idempotency key: одна operation;
- конкурентный захват: один owner, без двойной записи/потери ledger;
- явная независимая вставка с тем же brief: новый operation допустим;
- timeout после write: read-back находит сохранённый result, duplicate root не создаётся;
- post/root/fingerprint change: conflict/unknown без перезаписи чужих данных;
- stale acknowledgement: не завершает другую operation и не откатывает её;
- допустимые state transitions; terminal state не понижается старым сообщением.

Искусственный live provider-timeout с мутацией страницы не запускался: **NOT RUN**. Live stale pending проверен read-only на post=5214 и не вызвал новую запись.

## Live roots, copy и DOM

После v142 reload public preview `https://mazhenov.kz/pricing-contract-live-v123/?wpae_check=hero-b-132-2039` содержит:

- hero `a9282de`, background `rgb(246,240,230)`, copy `Тихая форма`, `АРХИТЕКТУРА`, `Пространство для идей`, `Опишите задачу и получите понятный первый шаг`;
- CTA `Начать проект` → `#contact`, `Смотреть проекты` → `#projects`;
- FAQ `13568dc` с исходным copy;
- pricing `b898e72`, background `rgb(255,255,255)`, три native cards и CTA `#start`, `#project`, `#support`;
- duplicate generated pricing root и временный `WPAE-UNSAVED-NEIGHBOR-*` marker отсутствуют.

Public DOM readback после v142:

| Root | Viewport/content width | Height | Background | Overflow |
|---|---:|---:|---|---|
| hero `a9282de` | `1265px` | `342px` | `rgb(246,240,230)` | false |
| FAQ `13568dc` | `1265px` | `305.203125px` | transparent | false |
| pricing `b898e72` | `1265px` | `429.375px` | `rgb(255,255,255)` | false |

Editor responsive readback:

- desktop inner width `1010px`: hero/pricing visible, no overflow;
- tablet `753px`: hero `flex-direction=column`, no overflow;
- mobile `345px`: copy first, visual zone second, both CTA usable, no horizontal overflow.

## Vision и screenshots

- Whole-operation Vision report for stale root: **BLOCKED before v142** by `wpae_vision_capture_failed`; no accepted durable Vision report exists.
- v142 guard prevents capture/provider call when target is absent; this is a truthful pending/unknown state, not visual acceptance.
- Selected patch Vision: score `100`, confidence `100%`, scoped element only.
- Fresh inline CUA screenshots after v142 editor reload/public preview were captured for editor desktop, editor mobile, public hero/FAQ, and public pricing. They correspond to post=5214, roots above, source/live v02.11.142.
- Filesystem PNG export: **SCREENSHOT BLOCKED**. Documented CUA exposes screenshot bytes through `getScreenshot()`/`emitImage()` only; no documented PNG writer or artifact export is available, and `tab.content.export()` is content export rather than screenshot bytes. No fake file links were created.

## Validation

```text
php -l includes/elementor/operation-ledger.php          PASS
php -l includes/elementor/editor-chat.php               PASS
php -l includes/llm/llm.php                             PASS
php -l wp-ai-executor.php                               PASS
php tests/design-pipeline-contract.php                  PASS (76 checks)
php tests/flex-generation-runtime.php                    PASS (331 checks)
node --test tests/*.test.js                             PASS (4 suites)
php docs/audits/2026-09-12/package-probe.php            PASS (90 files; mismatches=0)
git diff --check                                        PASS
```

## Status matrix

| Evidence | Status |
|---|---|
| v02.11.142 source | PASS |
| v02.11.142 active in WordPress | PASS |
| active/active routing without double write | PASS, harness + v140 trace |
| stale target classified before capture | PASS, editor pending button hidden |
| stale operation promoted to reviewed/completed | PASS prevention; remains written/pending |
| historical hero recovery and current copy | PASS |
| FAQ/pricing neighbor preservation | PASS |
| DOM desktop/tablet/mobile geometry | PASS |
| selected-scope Vision | PASS, 100/100 |
| whole-operation Vision | BLOCKED, no valid target/report |
| inline CUA screenshots | PASS |
| filesystem PNG links | BLOCKED by CUA capability |
| live artificial provider timeout | NOT RUN |
| cleanup of accidental inactive duplicate plugin | PENDING action-time confirmation |

## Commit, push and installation

- Final source commit: `4ae6f71cbdc0692efc1136f5766b7e0b6e2b1083`.
- Push: **PASS**, `438e5d9..4ae6f71 main -> main`.
- Active installation: **PASS**, WP Pusher updated active plugin to v02.11.142.
- Inactive duplicate installation: present from first malformed ZIP upload; no page/content data attached.
- Package/hash: **PASS**, 90 files, zero mismatches.
- Report/context updates are tracked separately; pre-existing untracked files are not staged.

Исторические v126/v140/v141 snapshots не являются доказательством текущего live состояния; этот документ содержит актуальный v142 срез.
