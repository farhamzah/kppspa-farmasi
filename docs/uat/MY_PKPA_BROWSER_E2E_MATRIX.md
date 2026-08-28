# MY PKPA Browser E2E Matrix

Framework: Playwright `@playwright/test`.

Command:
```bash
npm.cmd run e2e
```

Base URL:
```text
E2E_BASE_URL=http://127.0.0.1:3006
```

Viewport:
| Viewport | Size | Result |
|---|---:|---|
| Desktop | 1366x768 | Pass |
| Desktop Wide | 1920x1080 | Pass |
| Tablet | 768x1024 | Pass |
| Mobile | 390x844 | Pass |

Coverage:
| Area | Result |
|---|---|
| Guest landing, login, health, protected redirect | Pass |
| Admin PKPA management surfaces | Pass |
| Koordinator multi-role selector and management routes | Pass |
| Mahasiswa dashboard, rotation, academic, grade, final result, document | Pass |
| Pembimbing Dalam monitoring, academic, assessment | Pass |
| Preseptor operational, academic, assessment | Pass |
| Authorization negative checks | Pass |
| Export/download access control smoke | Pass |
| Horizontal overflow guard | Pass |
| Console error guard | Pass |
| Failed request guard | Pass |

