import unittest

from collector import Reading, parse_h5075

CASES = [
    ("live capture from GVH5075_3A14", "0003704c3700", Reading(22.5, 35.6, 55)),
    ("documented example payload", "0003d90d64", Reading(25.2, 17.3, 100)),
    ("negative temperature flagged by the top bit", "00818c4c5a", Reading(-10.1, 45.2, 90)),
    ("too short to hold a reading", "0003d9", None),
]


class ParseH5075Test(unittest.TestCase):
    def test_parse_h5075(self) -> None:
        for name, payload_hex, expected in CASES:
            with self.subTest(name):
                self.assertEqual(parse_h5075(bytes.fromhex(payload_hex)), expected)

    def test_missing_payload(self) -> None:
        self.assertEqual(parse_h5075(None), None)


if __name__ == "__main__":
    unittest.main()
