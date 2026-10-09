# ShopAI — Frontend (Nuxt)

Interface web de ShopAI : site vitrine, création de boutique assistée par l’IA, tableau de bord marchand, boutiques publiques et modération.

Stack : Nuxt 4 (SSR) · TypeScript · Bootstrap 5 · Bootstrap Icons.

## Installation

```bash
cd frontend
npm install
```

## Lancer en développement

```bash
npm run dev
```

L’application est disponible sur http://localhost:3000.

## Configuration

L’URL de l’API Laravel se règle avec la variable d’environnement `NUXT_PUBLIC_API_BASE`
(par défaut `http://localhost:8000/api/v1`) :

```bash
cp .env.example .env
# puis éditez NUXT_PUBLIC_API_BASE si besoin
```

## Production

```bash
npm run build
node .output/server/index.mjs
```
