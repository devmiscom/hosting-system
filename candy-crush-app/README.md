# 🍬 Candy Crush – React Native (Expo)

A minimal but fully functional **Candy Crush**-style match-3 mobile game built with **React Native + Expo**.

---

## Features

| Feature | Details |
|---|---|
| **Platform** | Android (React Native via Expo) |
| **Board** | 8 × 8 grid |
| **Match mechanics** | Swap adjacent candies, match 3+ in a row / column |
| **Special candies** | Striped (row/col blast), Wrapped (3×3 blast), Colour Bomb (wipes one colour) |
| **Score tracking** | Accumulating score with combo multiplier |
| **Level progression** | 10 levels, each with a score target and 25 moves |
| **Visual feedback** | Cascade animations, combo banner, progress bar, selected-candy glow |
| **Colours** | Poppy palette — Ruby, Tangerine, Lemon, Lime, Sapphire, Amethyst |

---

## Special Candy Guide

| Symbol | Name | Created by | Effect |
|---|---|---|---|
| ↔ | Striped H | Match exactly **4** horizontally | Clears the **entire row** |
| ↕ | Striped V | Match exactly **4** vertically | Clears the **entire column** |
| ✦ | Wrapped | Match in a **T or L shape** | Clears a **3 × 3** area |
| ★ | Colour Bomb | Match **5+ in a straight line** | Swapped with any candy → removes **all** of that colour |

---

## Getting Started

### Prerequisites

- [Node.js](https://nodejs.org/) 18+  
- [Expo Go](https://expo.dev/client) installed on your Android device (or an Android emulator)

### Install & Run

```bash
cd candy-crush-app
npm install
npx expo start
```

Scan the QR code in your terminal with the **Expo Go** app on your phone, or press **`a`** to open in an Android emulator.

### Build a Standalone APK

```bash
# Install EAS CLI once
npm install -g eas-cli

# Log in to your Expo account
eas login

# Build
eas build --platform android --profile preview
```

---

## Project Structure

```
candy-crush-app/
├── App.js                        # Navigation root
├── app.json                      # Expo config
├── babel.config.js
├── package.json
└── src/
    ├── constants/
    │   └── game.js               # Grid size, colours, level targets
    ├── utils/
    │   └── gameLogic.js          # Pure game logic (init, match, specials, gravity)
    ├── components/
    │   └── CandyPiece.js         # Single candy tile component
    └── screens/
        ├── HomeScreen.js         # Title & how-to-play screen
        └── GameScreen.js         # Main game loop + modals
```

---

## Game Rules

1. **Tap** a candy to select it (it glows white).  
2. **Tap** an adjacent candy to attempt a swap.  
3. If the swap creates a match of 3+, the match is removed and new candies fall from above.  
4. Chains (cascades) score bonus points with an increasing **combo multiplier** (×1.5 per cascade).  
5. Reach the **target score** within the allowed **moves** to advance to the next level.  
6. Running out of moves before the target → **Game Over**.  
7. If no valid moves exist, the board is **automatically reshuffled**.

---

## Tech Stack

- [React Native](https://reactnative.dev/) 0.73  
- [Expo SDK 50](https://docs.expo.dev/)  
- [React Navigation v6](https://reactnavigation.org/) (Stack)  
- Zero external game libraries – pure JS logic
