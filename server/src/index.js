import "dotenv/config";
import express from "express";
import { migrate } from "./db.js";
import { applicationsRouter } from "./routes/applications.js";
import { adminRouter } from "./routes/admin.js";

const app = express();
app.use(express.json());

app.get("/api/health", (req, res) => res.json({ ok: true }));
app.use("/api", applicationsRouter);
app.use("/admin", adminRouter);

// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ error: "internal_error" });
});

const port = Number(process.env.PORT) || 4000;
await migrate();
app.listen(port, () => console.log(`API listening on :${port}`));
