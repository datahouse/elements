/**
 * simulate data struct
 *
 * options:
 * nodeId              int       require
 * label               string    require
 * languages           array     require
 * nodes               array     optional
 * disableNesting      boolean   optional
 * nodesReadOnly       boolean   optional
 * disableDraggable    boolean   optional
 * nodesSortableOnly   boolean   optional
 * 
 * @return array 
 */
function getData() {
  // nodesSortableOnly: true

  return [
    {
      nodeId: 1,
      label: 'Home',
      languages: [
        {
          name: 'Deutsch',
          short: 'DE',
          status: 'published'
        },
        {
          name: 'Francaise',
          short: 'FR',
          status: 'edited'
        },
        {
          name: 'English',
          short: 'EN',
          status: 'edited'
        }
      ],
      disableDraggable: true,
      nodes: [
        {
          nodeId: 2,
          label: 'News',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ],
          nodes: [
            {
              nodeId: 3,
              label: '2016',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            },
            {
              nodeId: 4,
              label: '2015',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            }
          ]
        },
        {
          nodeId: 5,
          label: 'Über uns',
          nodesSortableOnly: true,
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ],
          nodes: [
            {
              nodeId: 6,
              label: 'Verwaltungsrat',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            },
            {
              nodeId: 7,
              label: 'Geschäftsleitung',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            },
            {
              nodeId: 8,
              label: 'Mitarbeiter',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            },
            {
              nodeId: 9,
              label: 'AGB',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            }
          ]
        },
        {
          nodeId: 10,
          label: 'Kontakt',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ],
          nodes: [
            {
              nodeId: 11,
              label: 'Standort Zürich',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            },
            {
              nodeId: 12,
              label: 'Standort Bern',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            }
          ]
        }
      ]
    },
    {
      nodeId: 13,
      label: 'Dienstleistungen',
      languages: [
        {
          name: 'Deutsch',
          short: 'DE',
          status: 'published'
        },
        {
          name: 'Francaise',
          short: 'FR',
          status: 'disabled'
        },
        {
          name: 'English',
          short: 'EN',
          status: 'published'
        }
      ],
      nodes: [
        {
          nodeId: 14,
          label: 'Beratung',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ]
        },
        {
          nodeId: 69,
          label: 'Umsetzung',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ]
        },
        {
          nodeId: 15,
          label: 'Betrieb und Support',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ]
        }
      ]
    },
    {
      nodeId: 16,
      label: 'Produkte',
      languages: [
        {
          name: 'Deutsch',
          short: 'DE',
          status: 'published'
        },
        {
          name: 'Francaise',
          short: 'FR',
          status: 'disabled'
        },
        {
          name: 'English',
          short: 'EN',
          status: 'published'
        }
      ],
      nodes: [
        {
          nodeId: 17,
          label: 'Monitoor',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ]
        },
        {
          nodeId: 18,
          label: 'Elements',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ]
        },
        {
          nodeId: 19,
          label: 'Online-Tools',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ],
          nodesReadOnly: true,
          nodes: [
            {
              nodeId: 20,
              label: 'Strassenverkehr',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ],
              nodes: [
                {
                  nodeId: 21,
                  label: 'Verkehrsabgaben',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 22,
                  label: 'Verkehrsabgabenvergleich',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                }
              ]
            },
            {
              nodeId: 23,
              label: 'Wohnen',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ],
              nodes: [
                {
                  nodeId: 24,
                  label: 'Eigentumsrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 25,
                  label: 'Gemeindevergleich',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 26,
                  label: 'Hypothekarrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 27,
                  label: 'Mietzinsrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                }
              ]
            },
            {
              nodeId: 28,
              label: 'Gesundheit',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ],
              nodes: [
                {
                  nodeId: 29,
                  label: 'Body Mass Index (BMI)',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 30,
                  label: 'Energieverbrauchsrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 31,
                  label: 'Fruchtbarkeitsrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 32,
                  label: 'Promillerechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 33,
                  label: 'Schwangerschaftsrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 34,
                  label: 'Flüssigkeitsbedarfsrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 35,
                  label: 'Trinkkalorienrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 36,
                  label: 'Waist-to-Hip ratio (WHR)',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                }
              ]
            },
            {
              nodeId: 37,
              label: 'Kommunikation',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ],
              nodes: [
                {
                  nodeId: 38,
                  label: 'URL-Shortener',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 39,
                  label: 'Textverschlüsselung',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                }
              ]
            },
            {
              nodeId: 40,
              label: 'Finanzen',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ],
              nodes: [
                {
                  nodeId: 41,
                  label: 'Familienzulagerechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 42,
                  label: 'Familienzulagevergleich',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 43,
                  label: 'Immobilienleasingrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 44,
                  label: 'Kreditrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 45,
                  label: 'Leasingrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 46,
                  label: 'Portorechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 47,
                  label: 'Sparrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 48,
                  label: 'Teuerungsrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                }
              ]
            },
            {
              nodeId: 49,
              label: 'Versicherung',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ],
              nodes: [
                {
                  nodeId: 50,
                  label: 'AHV-Beitragsrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 51,
                  label: 'Krankenkassenrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                }
              ]
            },
            {
              nodeId: 52,
              label: 'Steuert',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ],
              nodes: [
                {
                  nodeId: 53,
                  label: 'Quellensteuern',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 54,
                  label: 'Säule 3a',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 55,
                  label: 'Steuerranking',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 56,
                  label: 'Steuerrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 57,
                  label: 'Steuervergleich',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                },
                {
                  nodeId: 58,
                  label: 'Bruttosteuerrechner',
                  languages: [
                    {
                      name: 'Deutsch',
                      short: 'DE',
                      status: 'published'
                    },
                    {
                      name: 'Francaise',
                      short: 'FR',
                      status: 'disabled'
                    },
                    {
                      name: 'English',
                      short: 'EN',
                      status: 'published'
                    }
                  ]
                }
              ]
            }
          ]
        },
        {
          nodeId: 59,
          label: 'Data Management',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ],
          nodes: [
            {
              nodeId: 60,
              label: 'Informationsportal',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            },
            {
              nodeId: 61,
              label: 'Marketing Toolbox',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            },
            {
              nodeId: 62,
              label: 'Vertragsmanager',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            },
            {
              nodeId: 63,
              label: 'SLA Manager',
              languages: [
                {
                  name: 'Deutsch',
                  short: 'DE',
                  status: 'published'
                },
                {
                  name: 'Francaise',
                  short: 'FR',
                  status: 'disabled'
                },
                {
                  name: 'English',
                  short: 'EN',
                  status: 'published'
                }
              ]
            }
          ]
        },
        {
          nodeId: 64,
          label: 'Geocoding Service',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ]
        },
        {
          nodeId: 65,
          label: 'Cloud Storage',
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ]
        },
      ]
    },
    {
      nodeId: 66,
      label: 'Technologien',
      languages: [
        {
          name: 'Deutsch',
          short: 'DE',
          status: 'published'
        },
        {
          name: 'Francaise',
          short: 'FR',
          status: 'edited'
        },
        {
          name: 'English',
          short: 'EN',
          status: 'edited'
        }
      ]
    },
    {
      nodeId: 67,
      label: 'Kunden',
      languages: [
        {
          name: 'Deutsch',
          short: 'DE',
          status: 'published'
        },
        {
          name: 'Francaise',
          short: 'FR',
          status: 'disabled'
        },
        {
          name: 'English',
          short: 'EN',
          status: 'published'
        }
      ],
      nodes: [
        {
          nodeId: 68,
          label: 'Kundenliste',
          disableNesting: true,
          languages: [
            {
              name: 'Deutsch',
              short: 'DE',
              status: 'published'
            },
            {
              name: 'Francaise',
              short: 'FR',
              status: 'disabled'
            },
            {
              name: 'English',
              short: 'EN',
              status: 'published'
            }
          ]
        }
      ]
    }
  ];
}