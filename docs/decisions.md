# Журнал implementation decisions

Фиксируются только новые нетривиальные implementation decisions, которых нет в approved SOW, Architecture или DB specifications и которые влияют на последующие задачи.

## D-001 — Завершать production TLS на ingress перед Compose web

**Task:** TASK-011
**Дата:** 2026-10-22
**Статус:** принято

### Контекст

Production topology должна поддерживать HTTPS и WebSocket, но текущая Compose конфигурация не должна содержать сертификаты, cloud-provider API или отдельный цикл выдачи/обновления сертификатов.

### Решение

TLS завершается на внешнем Nginx ingress/reverse proxy или managed load balancer. Он проксирует HTTP к production Compose `web`, передаёт `X-Forwarded-Proto: https` и поддерживает WebSocket Upgrade для `/app/`. Доступ к Compose web port ограничивается источником ingress.

### Обоснование

Это сохраняет утверждённый Nginx + TLS topology, оставляет приложение и Compose provider-neutral и не добавляет в репозиторий сертификаты, ACME client или отдельный TLS subsystem.

### Рассмотренные альтернативы

- Завершать TLS непосредственно внутри Compose Nginx с host-mounted сертификатами и отдельной процедурой renewal.
- Добавить ACME/certificate automation service в Compose.

### Последствия

- Deployment требует настроенный ingress/TLS endpoint, домен и firewall rule.
- `APP_URL` задаётся как HTTPS, session secure cookies включены; ingress обязан передавать ожидаемый forwarded protocol.
- Этот выбор не предоставляет публичный target или сертификат сам по себе.

### Связанные спецификации

- SOW.md → «Cloud», «Docker», «Portability»
- architecture.md → «Production build», «Production topology», «Cloud portability»
- DB.md → N/A
