| Fejléc | Jelentés |
|---|---|
| `name: Cím;` | darab neve |
| `mode: 1;` | hangnem (1–8, vagy `t` = toni) |
| `initial-style: 1;` | iniciálé mérete (0=nincs, 1=normál, 2=nagy) |
| `office-part: introitus;` | liturgikus rész neve |
| `%%` | fejléc lezárása |

| Kulcsok | |
|---|---|
| `(c1)` `(c2)` `(c3)` `(c4)` | C-kulcs 1–4. vonalon |
| `(f3)` `(f4)` | F-kulcs 3–4. vonalon |
| `(cb3)` | C-kulcs b-előjegyzéssel |

| Hangmagasság | |
|---|---|
| `a b c d e f g h i j k l m` | 13 pozíció alulról felfelé |
| `(clef) betű` | pl. `(c3) f` = do (c-kulcs 3. vonalon, f pozíció) |

| Kottafej | |
|---|---|
| `f` | punctum (alapforma) |
| `f.` | punctum mora (megnyújtott) |
| `fv` | virga |
| `fo` | oriscus |
| `fq` | quilisma |
| `fw` | stropha |
| `fr` | liquescens (kicsi) |
| `fR` | liquescens (nagy) |

| Neuma-csoportok | |
|---|---|
| `fh` | podatus (két hang: alsó–felső) |
| `ghf` | torculus |
| `fhg` | porrectus (legato ívvel) |
| `fg` `gh` | clivis, podatus (explicit) |
| `!` | neumacsoporton belüli kötés kikapcsolása |

| Tagolás/ütem | |
|---|---|
| `,` | negyedvonal (kis cezúra) |
| `;` | félvonal |
| `:` | egész vonal |
| `::` | kettős vonal (rész vége) |
| `:::` | záróvonal |
| `,0` | láthatatlan szünetjel |

| Módosítójelek | |
|---|---|
| `(eb)` | b-módosítójel e hangon |
| `(ey)` | feloldójel e hangon |
| `(e#)` | kereszt e hangon |

| Szöveg | |
|---|---|
| `Ky(f)ri(h)e(g)` | szótag zárójelben lévő hanghoz kötve |
| `<sp>V/</sp>` | speciális karakter (verzikulus) |
| `<i>szöveg</i>` | dőlt szöveg |
| `<b>szöveg</b>` | félkövér szöveg |
| `<alt>szöveg</alt>` | másodlagos szövegsor (pl. fordítás) |

| Sortörés/rés | |
|---|---|
| `(z)` | sortörés |
| `(Z)` | sorkizárt sortörés |
| `(,)` | kis szóköz |
| `(//)` | szóköz |
